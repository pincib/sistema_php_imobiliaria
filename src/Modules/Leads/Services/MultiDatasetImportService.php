<?php

declare(strict_types=1);

namespace App\Modules\Leads\Services;

use App\Database\Connection;
use App\Audit\AuditService;
use App\Modules\Leads\Pipeline\PipelineManager;
use App\Modules\Leads\Repositories\ImportBatchRepository;
use App\Modules\Leads\Support\LeadNormalizer;
use InvalidArgumentException;
use PDO;

/** Shared dry-run and transactional writer for the three legacy CSV datasets. */
final class MultiDatasetImportService
{
    private PDO $pdo;
    private ImportBatchRepository $batches;
    public function __construct(?PDO $pdo = null, ?ImportBatchRepository $batches = null)
    { $this->pdo = $pdo ?? Connection::getPdo(); $this->batches = $batches ?? new ImportBatchRepository($this->pdo); }

    public function detect(array $headers): ?string
    {
        $h = array_map(static fn($v) => mb_strtolower(trim((string)$v)), $headers);
        if ((in_array('codigo', $h, true) || in_array('ref.', $h, true)) && in_array('logradouro', $h, true)) return 'properties';
        if (in_array('codigo_imovel', $h, true) && in_array('name', $h, true)) return null;
        if (in_array('ref. imóvel', $h, true) && in_array('telefones', $h, true)) return 'owners';
        foreach ($h as $header) {
            if (preg_match('/^(nome|name|cliente|contato|lead|first name)$/u', $header)
                || preg_match('/(telefone|celular|fone|tel|phone|whatsapp|zap|e-?mail|correio|origem|source|canal|notas|notes)/u', $header)) {
                return 'leads';
            }
        }
        return null;
    }

    public function suggestedMapping(array $headers, string $kind): array
    {
        $map = []; $find = static function(array $names) use ($headers): ?int {
            foreach ($headers as $i => $h) if (in_array(mb_strtolower(trim((string)$h)), $names, true)) return $i;
            return null;
        };
        if ($kind === 'properties') foreach (['code'=>['codigo','ref.'],'type'=>['tipo'],'area'=>['area'],'intention'=>['intensao'],'aspiration'=>['pretensao'],'sale'=>['valor_venda','venda'],'rent'=>['valor_locacao','locação'],'address'=>['logradouro'],'neighborhood'=>['bairro'],'city'=>['cidade'],'postal_code'=>['cep'],'iptu'=>['iptu']] as $k=>$v) { $i=$find($v); if($i!==null)$map[$k]=$i; }
        elseif ($kind === 'owners') foreach (['name'=>['name','nome'],'phone'=>['telefone','telefones'],'email'=>['email','e-mail'],'property_code'=>['codigo_imovel','ref. imóvel']] as $k=>$v) { $i=$find($v); if($i!==null)$map[$k]=$i; }
        else foreach (['name'=>['name','nome'],'property_ref'=>['codigo_imovel'],'email'=>['email'],'phone'=>['telefone']] as $k=>$v) { $i=$find($v); if($i!==null)$map[$k]=$i; }
        return $map;
    }

    public function analyze(string $path, string $kind, array $mapping, string $delimiter, string $filename, string $zeroPolicy = 'absent'): array
    {
        $parsed=$this->rows($path,$delimiter);$rows=$parsed['rows'];$headers=$parsed['headers']; $errors=[]; $dupes=[]; $ready=[]; $seenProperties=[];$seenOwnerNames=[];$seenOwnerPhones=[];$seenOwnerEmails=[];$seenLeadPhones=[];$seenLeadEmails=[];
        foreach (array_slice($rows, 0, LeadImportService::MAX_ROWS) as $n=>$row) {
            $line=$n+2; $v=static fn($key)=>isset($mapping[$key])?trim((string)($row[(int)$mapping[$key]]??'')):'';
            // Keep source columns positional: CSVs may contain duplicate or blank headers.
            $sourceRaw=[];foreach($headers as $index=>$header)$sourceRaw[]=['index'=>$index,'header'=>(string)$header,'value'=>(string)($row[$index]??'')];
            if($kind==='properties') {
                $code=$v('code'); $address=$v('address'); $sale=$this->money($v('sale'),$zeroPolicy); $rent=$this->money($v('rent'),$zeroPolicy); $iptu=$this->money($v('iptu'),$zeroPolicy); $area=$this->measure($v('area'));
                $reason=''; if($code==='')$reason='Referência do imóvel vazia.'; elseif($address==='')$reason='Logradouro vazio.'; elseif($v('type')==='')$reason='Tipo vazio.'; elseif($sale===false||$rent===false||$iptu===false||$area===false)$reason='Valor monetário ou área inválida.';
                if($reason!==''){$errors[]=['row'=>$line,'reason'=>$reason];continue;}
                $existing=$this->findProperty($code); $duplicate=$existing!==null||isset($seenProperties[mb_strtolower($code)]); $seenProperties[mb_strtolower($code)]=true;
                $ready[]=['row'=>$line,'kind'=>$kind,'code'=>$code,'title'=>$address,'type'=>$v('type'),'sale'=>$sale,'rent'=>$rent,'area'=>$area,'intention'=>$v('intention'),'aspiration'=>$v('aspiration'),'address'=>$address,'neighborhood'=>$v('neighborhood'),'city'=>$v('city'),'postal_code'=>$v('postal_code'),'iptu'=>$iptu,'raw'=>$sourceRaw,'duplicate'=>$duplicate,'existing'=>$existing];
            } elseif($kind==='owners') {
                $name=$v('name');$code=$v('property_code');$phone=LeadNormalizer::normalizePhone($v('phone'));$email=LeadNormalizer::normalizeEmail($v('email'));
                $prop=$code!==''?$this->findProperty($code):null;$reason=$name===''?'Nome do proprietário vazio.':($code===''?'Referência do imóvel vazia.':'');
                if($reason!==''){$errors[]=['row'=>$line,'reason'=>$reason];continue;}
                $owner=$this->findOwner($name,$phone,$email);$nameKey=mb_strtolower($name);$dup=$owner!==null||isset($seenOwnerNames[$nameKey])||($phone!==null&&isset($seenOwnerPhones[$phone]))||($email!==null&&isset($seenOwnerEmails[$email]));$seenOwnerNames[$nameKey]=true;if($phone!==null)$seenOwnerPhones[$phone]=true;if($email!==null)$seenOwnerEmails[$email]=true;
                $ready[]=['row'=>$line,'kind'=>$kind,'name'=>$name,'phone'=>$phone,'email'=>$email,'property_id'=>$prop?(int)$prop['id']:null,'code'=>$code,'raw'=>$sourceRaw,'duplicate'=>$dup,'existing'=>$owner];
            } else {
                $rawLast=$v('last_name');$suspectedRef=preg_match('/^[A-Z]{2}\d{4}$/i',$rawLast)===1?$rawLast:'';
                $name=$v('name') ?: implode(' ',array_filter([$v('first_name'),$v('middle_name'),$suspectedRef===''?$rawLast:''],static fn($x)=>$x!==''));
                $phone=LeadNormalizer::normalizePhone($v('phone'));$email=LeadNormalizer::normalizeEmail($v('email'));
                $alternatePhone=LeadNormalizer::normalizePhone($v('alternate_phone'));$alternateEmail=LeadNormalizer::normalizeEmail($v('alternate_email'));
                $reason=$name===''?'Nome composto vazio.':((!preg_match('/\p{L}{2}/u',$name)||preg_match('/\d{3,}/u',$name))?'Nome parece conter apenas um código ou telefone; confira o mapeamento.':(($email!==null&&!filter_var($email,FILTER_VALIDATE_EMAIL))?'E-mail principal inválido.':(($phone!==null&&(strlen($phone)<8||strlen($phone)>13))?'Telefone principal inválido.':'')));
                if($reason!==''){$errors[]=['row'=>$line,'reason'=>$reason];continue;}
                $phones=array_values(array_unique(array_filter([$phone,$alternatePhone],static fn($value)=>$value!==null&&$value!=='')));
                $emails=array_values(array_unique(array_filter([$email,$alternateEmail],static fn($value)=>$value!==null&&$value!=='')));
                $existing=$this->findLead($phone,$email,$alternatePhone,$alternateEmail);
                $dup=$existing!==null;
                foreach($phones as $candidate) if(isset($seenLeadPhones[$candidate])) $dup=true;
                foreach($emails as $candidate) if(isset($seenLeadEmails[$candidate])) $dup=true;
                foreach($phones as $candidate) $seenLeadPhones[$candidate]=true;
                foreach($emails as $candidate) $seenLeadEmails[$candidate]=true;
                $ready[]=['row'=>$line,'kind'=>$kind,'name'=>$name,'phone'=>$phone,'email'=>$email,'alternate_phone'=>$alternatePhone,'alternate_email'=>$alternateEmail,'notes'=>$v('notes'),'property_ref'=>$v('property_ref'),'suspected_ref'=>$suspectedRef,'raw'=>$sourceRaw,'duplicate'=>$dup,'existing'=>$existing];
            }
            if($ready && end($ready)['duplicate'])$dupes[]=['row'=>$line,'reason'=>'Registro repetido no arquivo ou já existente no CRM.'];
        }
        $warnings=[]; if($kind==='leads') foreach($ready as $r) { if($r['suspected_ref']!=='')$warnings[]=['row'=>$r['row'],'reason'=>'O valor "'.$r['suspected_ref'].'" no campo Last Name parece uma referência; foi preservado como origem e não usado para associar imóvel.']; if($r['property_ref']==='') $warnings[]=['row'=>$r['row'],'reason'=>'Sem vínculo: código do imóvel não informado.']; elseif(!$this->findProperty($r['property_ref'])) $warnings[]=['row'=>$r['row'],'reason'=>'Imóvel '.$r['property_ref'].' ainda não cadastrado; o vínculo será criado quando a planilha de imóveis for importada.']; }
        if($kind==='owners') foreach($ready as $r) if(!$r['property_id']) $warnings[]=['row'=>$r['row'],'reason'=>'Imóvel '.$r['code'].' ainda não cadastrado; o vínculo será criado quando a planilha de imóveis for importada.'];
        if($kind==='properties') foreach($ready as $r) if(!in_array(mb_strtolower($r['type']),['loja','casa','chácara','flat','galpão','terreno','área','andar corporativo','apartamento','cobertura','ponto','prédio','sala','comercial'],true)) $warnings[]=['row'=>$r['row'],'reason'=>'Tipo "'.$r['type'].'" será classificado como Comercial; o texto original ficará preservado na linha de origem.'];
        if(count($rows)>LeadImportService::MAX_ROWS)$errors[]=['row'=>LeadImportService::MAX_ROWS+2,'reason'=>'Limite de 5.000 linhas por lote excedido; linhas após o limite não serão processadas.'];
        return ['total_rows'=>count($rows),'ready'=>$ready,'errors_list'=>$errors,'duplicates_list'=>$dupes,'warnings_list'=>array_slice($warnings,0,100),'warnings_count'=>count($warnings),'new_count'=>count(array_filter($ready,fn($r)=>!$r['duplicate'])),'duplicate_count'=>count($dupes),'error_count'=>count($errors),'kind'=>$kind,'filename'=>$filename,'zero_policy'=>$zeroPolicy];
    }

    public function execute(array $analysis, string $filename, string $hash, int $actor, string $duplicatePolicy = 'merge'): array
    {
        $batchCode='IMP-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4));
        return Connection::transaction(function(PDO $pdo)use($analysis,$filename,$hash,$actor,$batchCode,$duplicatePolicy){
            $batch=$this->batches->create(['batch_code'=>$batchCode,'user_id'=>$actor,'filename'=>$filename,'file_hash'=>$hash,'file_size'=>0,'duplicate_policy'=>'merge','status'=>'processing','data_kind'=>$analysis['kind']]);
            $created=0;$updated=0;$skipped=0;$linked=0;
            foreach($analysis['ready'] as $r){
                if($r['duplicate']&&empty($r['existing']))$r['existing']=$analysis['kind']==='properties'?$this->findProperty($r['code']):($analysis['kind']==='owners'?$this->findOwner($r['name'],$r['phone'],$r['email']):$this->findLead($r['phone'],$r['email'],$r['alternate_phone']??null,$r['alternate_email']??null));
                if($r['duplicate']&&$duplicatePolicy==='skip'){$entity=$analysis['kind']==='properties'?'property':($analysis['kind']==='owners'?'owner':'lead');$entityId=$r['existing']['id']??null;$ref=$analysis['kind']==='properties'?$r['code']:($analysis['kind']==='owners'?$r['code']:($r['property_ref']?:$r['suspected_ref']));$source=$pdo->prepare('INSERT INTO import_source_rows(batch_id,entity_type,entity_id,external_reference,source_filename,source_row,source_data_json) VALUES(:batch,:type,:entity,:ref,:file,:row,:json)');$source->execute([':batch'=>$batch,':type'=>$entity,':entity'=>$entityId,':ref'=>$ref,':file'=>$filename,':row'=>$r['row'],':json'=>json_encode($r['raw'],JSON_UNESCAPED_UNICODE)]);$skipped++;continue;}
                if($r['duplicate'] && $analysis['kind']==='properties') {
                    $id=(int)$r['existing']['id']; $stmt=$pdo->prepare('UPDATE properties SET title=:title,type=:type,price=COALESCE(:price,price),sale_price=COALESCE(:sale,sale_price),rent_price=COALESCE(:rent,rent_price),iptu_price=COALESCE(:iptu,iptu_price),address=COALESCE(:address,address),neighborhood=COALESCE(:neighborhood,neighborhood),city=COALESCE(:city,city),postal_code=COALESCE(:postal,postal_code),intention=COALESCE(:intention,intention),aspiration=COALESCE(:aspiration,aspiration),built_area=COALESCE(:built,built_area),land_area=COALESCE(:land,land_area),updated_at=CURRENT_TIMESTAMP WHERE id=:id');
                    $isLand=in_array($this->propertyType($r['type']),['terreno','area'],true);$stmt->execute([':title'=>$r['title'],':type'=>$this->propertyType($r['type']),':price'=>$r['sale']??$r['rent'],':sale'=>$r['sale'],':rent'=>$r['rent'],':iptu'=>$r['iptu'],':address'=>$r['address'],':neighborhood'=>$r['neighborhood'],':city'=>$r['city'],':postal'=>$r['postal_code'],':intention'=>$this->intention($r['intention'],$r['sale'],$r['rent']),':aspiration'=>$this->aspiration($r['aspiration']),':built'=>$isLand?null:$r['area'],':land'=>$isLand?$r['area']:null,':id'=>$id]);$updated++;
                } elseif($analysis['kind']==='properties') {
                    $stmt=$pdo->prepare('INSERT INTO properties(code,title,type,price,status,address,neighborhood,city,intention,sale_price,rent_price,iptu_price,postal_code,built_area,land_area,aspiration,import_batch_id) VALUES(:code,:title,:type,:price,:status,:address,:neighborhood,:city,:intent,:sale,:rent,:iptu,:postal,:built,:land,:aspiration,:batch)');
                    $intent=$this->intention($r['intention'],$r['sale'],$r['rent']);$isLand=in_array($this->propertyType($r['type']),['terreno','area'],true);$stmt->execute([':code'=>$r['code'],':title'=>$r['title'],':type'=>$this->propertyType($r['type']),':price'=>$r['sale']??$r['rent'],':status'=>'disponivel',':address'=>$r['address'],':neighborhood'=>$r['neighborhood']?:null,':city'=>$r['city']?:null,':intent'=>$intent,':sale'=>$r['sale'],':rent'=>$r['rent'],':iptu'=>$r['iptu'],':postal'=>$r['postal_code']?:null,':built'=>$isLand?null:$r['area'],':land'=>$isLand?$r['area']:null,':aspiration'=>$this->aspiration($r['aspiration']),':batch'=>$batch]);$id=(int)$pdo->lastInsertId();$created++;
                } elseif($analysis['kind']==='owners') {
                    $owner=$r['existing']; if(!$owner){$stmt=$pdo->prepare('INSERT INTO owners(name,email,phone,import_batch_id) VALUES(:name,:email,:phone,:batch)');try{$stmt->execute([':name'=>$r['name'],':email'=>$r['email'],':phone'=>$r['phone'],':batch'=>$batch]);$owner=['id'=>(int)$pdo->lastInsertId()];$created++;}catch(\PDOException){$owner=$this->findOwner($r['name'],$r['phone'],$r['email']);if(!$owner){$skipped++;continue;}}}else { if($duplicatePolicy==='merge'){$up=$pdo->prepare('UPDATE owners SET email=COALESCE(email,:email),phone=COALESCE(phone,:phone),updated_at=CURRENT_TIMESTAMP WHERE id=:id');$up->execute([':email'=>$r['email'],':phone'=>$r['phone'],':id'=>$owner['id']]);} $updated++; }
                    if($r['property_id']){$stmt=$pdo->prepare('INSERT OR IGNORE INTO owner_properties(owner_id,property_id,linked_by_user_id,import_batch_id) VALUES(:owner,:property,:actor,:batch)');$stmt->execute([':owner'=>$owner['id'],':property'=>$r['property_id'],':actor'=>$actor,':batch'=>$batch]);if($stmt->rowCount())$linked++;}
                    $id=(int)$owner['id'];
                } else {
                    if($r['duplicate']){$id=(int)$r['existing']['id'];$createdInThisBatch=(int)($r['existing']['import_batch_id']??0)===$batch;$up=$pdo->prepare('UPDATE leads SET email=COALESCE(email,:email),phone=COALESCE(phone,:phone),alternate_email=COALESCE(alternate_email,:alt_email),alternate_phone=COALESCE(alternate_phone,:alt_phone),notes=CASE WHEN :notes IS NOT NULL AND notes IS NULL THEN :notes ELSE notes END,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$up->execute([':email'=>$r['email'],':phone'=>$r['phone'],':alt_email'=>$r['alternate_email']?:null,':alt_phone'=>$r['alternate_phone']?:null,':notes'=>$r['notes']?:null,':id'=>$id]);$updated++;if(!$createdInThisBatch){$event=$pdo->prepare("INSERT INTO lead_interactions(lead_id,user_id,type,notes,contacted_at,created_at) VALUES(:lead,:user,'importacao',:notes,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");$event->execute([':lead'=>$id,':user'=>$actor,':notes'=>'Dados conferidos/atualizados pela importação CSV '.$batchCode]);}}
                    else {$stmt=$pdo->prepare('INSERT INTO leads(name,email,phone,alternate_email,alternate_phone,source,stage,user_id,notes,import_batch_id) VALUES(:name,:email,:phone,:alt_email,:alt_phone,:source,:stage,:user,:notes,:batch)');$stmt->execute([':name'=>$r['name'],':email'=>$r['email'],':phone'=>$r['phone'],':alt_email'=>$r['alternate_email']?:null,':alt_phone'=>$r['alternate_phone']?:null,':source'=>'importacao_csv',':stage'=>PipelineManager::STAGE_NOVO,':user'=>$actor,':notes'=>$r['notes']?:null,':batch'=>$batch]);$id=(int)$pdo->lastInsertId();$created++;}
                    if($r['property_ref']!==''){$property=$this->findProperty($r['property_ref']);if($property){$link=$pdo->prepare("INSERT OR IGNORE INTO lead_properties(lead_id,property_id,interest_type,import_batch_id) VALUES(:lead,:property,'interesse',:batch)");$link->execute([':lead'=>$id,':property'=>$property['id'],':batch'=>$batch]);if($link->rowCount())$linked++;}}
                }
                if($analysis['kind']==='properties')$linked+=$this->resolvePendingLinks($pdo,$r['code'],(int)$id);
                $entity=$analysis['kind']==='properties'?'property':($analysis['kind']==='owners'?'owner':'lead');$ref=$analysis['kind']==='properties'?$r['code']:($analysis['kind']==='owners'?$r['code']:($r['property_ref']?:$r['suspected_ref']));
                $source=$pdo->prepare('INSERT INTO import_source_rows(batch_id,entity_type,entity_id,external_reference,source_filename,source_row,source_data_json) VALUES(:batch,:type,:entity,:ref,:file,:row,:json)');$source->execute([':batch'=>$batch,':type'=>$entity,':entity'=>$id,':ref'=>$ref,':file'=>$filename,':row'=>$r['row'],':json'=>json_encode($r['raw'],JSON_UNESCAPED_UNICODE)]);
            }
            $invalid=(int)$analysis['error_count'];$total=(int)$analysis['total_rows'];$this->batches->update($batch,['total_rows'=>$total,'created_count'=>$created,'updated_count'=>$updated,'skipped_count'=>$skipped,'error_count'=>$invalid,'status'=>'completed','cleanup_supported'=>1,'summary_json'=>json_encode(['linked'=>$linked,'kind'=>$analysis['kind'],'errors'=>$analysis['errors_list'],'duplicates'=>$analysis['duplicates_list']],JSON_UNESCAPED_UNICODE)]);
            AuditService::log('csv.import','import_batch',(string)$batch,null,['batch_code'=>$batchCode,'kind'=>$analysis['kind'],'total'=>$total,'inserted'=>$created,'updated'=>$updated,'skipped'=>$skipped,'invalid'=>$invalid,'linked'=>$linked],$actor);
            return ['success'=>true,'batch_id'=>$batch,'batch_code'=>$batchCode,'total_rows'=>$total,'created_count'=>$created,'updated_count'=>$updated,'skipped_count'=>$skipped,'error_count'=>$invalid,'linked_count'=>$linked,'kind'=>$analysis['kind']];
        });
    }

    private function resolvePendingLinks(PDO $pdo,string $code,int $propertyId):int
    {
        $stmt=$pdo->prepare("SELECT DISTINCT s.entity_type,s.entity_id,s.batch_id,b.user_id FROM import_source_rows s JOIN import_batches b ON b.id=s.batch_id WHERE s.external_reference=:code COLLATE NOCASE AND s.entity_id IS NOT NULL AND b.cleanup_supported=1 AND b.cleaned_at IS NULL AND ((s.entity_type='lead' AND b.data_kind='leads') OR (s.entity_type='owner' AND b.data_kind='owners'))");
        $stmt->execute([':code'=>$code]);$linked=0;
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            if($row['entity_type']==='lead'){$link=$pdo->prepare("INSERT OR IGNORE INTO lead_properties(lead_id,property_id,interest_type,import_batch_id) VALUES(:entity,:property,'interesse',:batch)");}
            else{$link=$pdo->prepare('INSERT OR IGNORE INTO owner_properties(owner_id,property_id,linked_by_user_id,import_batch_id) VALUES(:entity,:property,:actor,:batch)');}
            $params=[':entity'=>(int)$row['entity_id'],':property'=>$propertyId,':batch'=>(int)$row['batch_id']];if($row['entity_type']==='owner')$params[':actor']=$row['user_id']===null?null:(int)$row['user_id'];
            $link->execute($params);$linked+=$link->rowCount();
        }
        return $linked;
    }

    private function rows(string $path,string $delimiter):array{$h=fopen($path,'r');if(!$h)return['headers'=>[],'rows'=>[]];$headers=fgetcsv($h,0,$delimiter,'"','\\')?:[];if(isset($headers[0]))$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$headers[0]);$rows=[];while(($r=fgetcsv($h,0,$delimiter,'"','\\'))!==false){if(array_filter($r,fn($v)=>trim((string)$v)!==''))$rows[]=$r;}fclose($h);return ['headers'=>$headers,'rows'=>$rows];}
    private function money(string $v,string $zeroPolicy):float|false|null{if($v==='')return null;$v=str_replace([' ',"\u{00A0}"],'',$v);if(!preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/',$v))return false;$n=(float)str_replace(',','.',str_replace('.','',$v));return $n===0.0&&$zeroPolicy==='absent'?null:$n;}
    private function measure(string $v):float|false|null{if($v==='')return null;$v=str_replace([' ',"\u{00A0}"],'',$v);if(!preg_match('/^\d+(?:[.,]\d{1,3})?$/',$v))return false;$n=(float)str_replace(',','.',$v);return is_finite($n)?$n:false;}
    private function findProperty(string $code):?array{$s=$this->pdo->prepare('SELECT * FROM properties WHERE code=:code COLLATE NOCASE');$s->execute([':code'=>$code]);return $s->fetch()?:null;}
    private function findOwner(string $name,?string $phone,?string $email):?array{$s=$this->pdo->prepare('SELECT * FROM owners WHERE name=:name COLLATE NOCASE OR (:phone IS NOT NULL AND phone=:phone) OR (:email IS NOT NULL AND email=:email COLLATE NOCASE) LIMIT 1');$s->execute([':name'=>$name,':phone'=>$phone,':email'=>$email]);return $s->fetch()?:null;}
    private function findLead(?string $phone,?string $email,?string $alternatePhone=null,?string $alternateEmail=null):?array{$s=$this->pdo->prepare('SELECT * FROM leads WHERE (:phone IS NOT NULL AND (phone=:phone OR alternate_phone=:phone)) OR (:email IS NOT NULL AND (email=:email COLLATE NOCASE OR alternate_email=:email COLLATE NOCASE)) OR (:alternate_phone IS NOT NULL AND (phone=:alternate_phone OR alternate_phone=:alternate_phone)) OR (:alternate_email IS NOT NULL AND (email=:alternate_email COLLATE NOCASE OR alternate_email=:alternate_email COLLATE NOCASE)) LIMIT 1');$s->execute([':phone'=>$phone,':email'=>$email,':alternate_phone'=>$alternatePhone,':alternate_email'=>$alternateEmail]);return $s->fetch()?:null;}
    private function propertyType(string $type):string{$n=mb_strtolower($type);$map=['andar corporativo'=>'andar_corporativo','galpão'=>'galpao','prédio'=>'predio','área'=>'area','sala comercial'=>'sala','apartamento'=>'apartamento','casa'=>'casa','terreno'=>'terreno','loja'=>'loja','sala'=>'sala','cobertura'=>'cobertura','ponto'=>'ponto','comercial'=>'comercial','chácara'=>'casa','flat'=>'apartamento','ilha'=>'comercial'];return $map[$n]??'comercial';}
    private function intention(string $value,?float $sale,?float $rent):string{$v=mb_strtolower(trim($value));if(str_contains($v,'loca')&&str_contains($v,'vend'))return 'venda_locacao';if(str_contains($v,'loca'))return 'locacao';if(str_contains($v,'vend'))return 'venda';return $sale!==null&&$rent!==null?'venda_locacao':($rent!==null?'locacao':'venda');}
    private function aspiration(string $value):?string{return match(mb_strtolower(trim($value))){'residencial'=>'residencial','comercial'=>'comercial',default=>null};}
}
