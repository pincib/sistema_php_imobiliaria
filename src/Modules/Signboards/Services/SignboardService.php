<?php

declare(strict_types=1);

namespace App\Modules\Signboards\Services;

use App\Audit\AuditService;
use App\Database\Connection;
use App\Modules\Signboards\Repositories\SignboardRepository;
use DateTimeImmutable;

final class SignboardService
{
    public const STATUSES = ['ativa'=>'Ativa', 'vencida'=>'Vencida', 'pendente'=>'Pendente', 'retirada'=>'Retirada'];

    private SignboardRepository $repository;

    public function __construct(?SignboardRepository $repository = null)
    {
        $this->repository = $repository ?? new SignboardRepository();
    }

    public function repository(): SignboardRepository { return $this->repository; }

    public function getOverview(): array
    {
        return ['total'=>$this->repository->count(), 'statuses'=>$this->repository->countByStatus()];
    }

    public function validate(array $input): array
    {
        $errors = [];
        if ($this->positiveInt($input['property_id'] ?? null) === null) $errors['property_id'] = 'Selecione o imóvel vinculado.';
        $location = $this->text($input, 'location');
        if ($location === '' || mb_strlen($location) > 250) $errors['location'] = 'Informe o ponto da instalação (até 250 caracteres).';
        if ($this->positiveInt($input['responsible_user_id'] ?? null) === null) $errors['responsible_user_id'] = 'Selecione o responsável.';
        if (mb_strlen($this->text($input, 'notes')) > 2000) $errors['notes'] = 'As observações devem ter até 2.000 caracteres.';
        if (mb_strlen($this->text($input, 'confirmation_notes')) > 500) $errors['confirmation_notes'] = 'A observação de confirmação deve ter até 500 caracteres.';
        $date = $this->text($input, 'expires_at');
        if ($date !== '' && !$this->validDate($date)) $errors['expires_at'] = 'Informe uma data de validade válida.';
        return $errors;
    }

    public function save(array $input, int $actorId, ?int $id = null): array
    {
        $errors = $this->validate($input);
        if ($errors) return ['success'=>false, 'errors'=>$errors];
        $propertyId = $this->positiveInt($input['property_id']);
        $responsibleId = $this->positiveInt($input['responsible_user_id']);
        if (!$this->repository->validReferences($propertyId, $responsibleId)) return ['success'=>false, 'errors'=>['general'=>'Selecione um imóvel cadastrado e um responsável ativo.']];
        $data = [':property_id'=>$propertyId, ':location'=>$this->text($input, 'location'), ':responsible_user_id'=>$responsibleId, ':expires_at'=>$this->text($input, 'expires_at') ?: null, ':notes'=>$this->text($input, 'notes') ?: null];
        $normalized = array_combine(['property_id','location','responsible_user_id','expires_at','notes'], array_values($data));
        if ($id === null) {
            $confirmation = $this->text($input, 'confirmation_notes');
            $newId = Connection::transaction(function () use ($data, $actorId, $confirmation, $normalized): int {
                $newId = $this->repository->create($data + [':created_by_user_id'=>$actorId]);
                $this->repository->addMovement($newId, 'created', date('Y-m-d'), $confirmation !== '' ? $confirmation : 'Cadastro criado.', $actorId);
                AuditService::log('signboard.create','signboard',(string)$newId,null,$normalized,$actorId);
                return $newId;
            });
            return ['success'=>true, 'signboard_id'=>$newId];
        }
        $old = $this->repository->find($id);
        if (!$old) return ['success'=>false,'errors'=>['general'=>'Placa não encontrada.']];
        if ($old['removed_at'] !== null) return ['success'=>false,'errors'=>['general'=>'Placas retiradas não podem ser editadas.']];
        $before = array_intersect_key($old, $normalized);
        if ($before !== $normalized) {
            $confirmation = $this->text($input, 'confirmation_notes');
            $updated = Connection::transaction(function () use ($id, $data, $before, $normalized, $confirmation, $actorId): bool {
                if (!$this->repository->update($id, $data)) return false;
                $this->repository->addMovement($id, 'updated', date('Y-m-d'), $confirmation !== '' ? $confirmation : 'Cadastro atualizado.', $actorId);
                AuditService::log('signboard.update','signboard',(string)$id,$before,$normalized,$actorId);
                return true;
            });
            if (!$updated) return ['success'=>false,'errors'=>['general'=>'A placa foi retirada durante a edição. Atualize a tela e tente novamente.']];
        }
        return ['success'=>true, 'signboard_id'=>$id];
    }

    public function install(int $id, string $date, string $notes, int $actorId): array
    {
        $row = $this->repository->find($id);
        if (!$row || $row['removed_at'] !== null) return ['success'=>false,'error'=>'Placa indisponível para instalação.'];
        if ($row['installed_at'] !== null) return ['success'=>false,'error'=>'Esta placa já foi instalada.'];
        if (!$this->validDate($date) || $date > date('Y-m-d')) return ['success'=>false,'error'=>'Informe uma data de instalação válida, não futura.'];
        $notes=trim($notes);
        if ($notes === '') return ['success'=>false,'error'=>'Confirme a instalação com uma observação.'];
        if (mb_strlen($notes)>1000) return ['success'=>false,'error'=>'A confirmação deve ter até 1.000 caracteres.'];
        $installed = Connection::transaction(function () use ($id,$date,$notes,$actorId): bool {
            if (!$this->repository->markInstalled($id,$actorId,$date,$notes)) return false;
            AuditService::log('signboard.install','signboard',(string)$id,null,['installed_at'=>$date,'confirmation'=>$notes],$actorId);
            return true;
        });
        if (!$installed) return ['success'=>false,'error'=>'A placa foi alterada por outra pessoa. Atualize a tela antes de tentar novamente.'];
        return ['success'=>true];
    }

    public function remove(int $id, string $date, string $notes, int $actorId): array
    {
        $row = $this->repository->find($id);
        if (!$row || $row['removed_at'] !== null) return ['success'=>false,'error'=>'Esta placa já foi retirada ou não existe.'];
        if ($row['installed_at'] === null) return ['success'=>false,'error'=>'Instale a placa antes de registrar a retirada.'];
        if (!$this->validDate($date) || $date > date('Y-m-d') || $date < substr((string)$row['installed_at'],0,10)) return ['success'=>false,'error'=>'A data de retirada deve ser válida e igual ou posterior à instalação.'];
        $notes=trim($notes);
        if ($notes === '') return ['success'=>false,'error'=>'Confirme a retirada com uma observação.'];
        if (mb_strlen($notes)>1000) return ['success'=>false,'error'=>'A confirmação deve ter até 1.000 caracteres.'];
        $removed = Connection::transaction(function () use ($id,$date,$notes,$actorId): bool {
            if (!$this->repository->markRemoved($id,$actorId,$date,$notes)) return false;
            AuditService::log('signboard.remove','signboard',(string)$id,null,['removed_at'=>$date,'confirmation'=>$notes],$actorId);
            return true;
        });
        if (!$removed) return ['success'=>false,'error'=>'A placa foi alterada por outra pessoa. Atualize a tela antes de tentar novamente.'];
        return ['success'=>true];
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        return is_string($value) || is_numeric($value) ? trim((string)$value) : '';
    }

    private function positiveInt(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        return $validated === false ? null : (int)$validated;
    }
}
