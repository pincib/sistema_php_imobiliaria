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
    public const BOARD_STAGES = ['a_fazer'=>'A fazer', 'pago'=>'Pago', 'pronto'=>'Pronto', 'disponivel'=>'Disponível', 'usadas'=>'Usadas'];
    public const MATERIALS = ['lona'=>'Lona', 'adesivo'=>'Adesivo', 'outro'=>'Outro'];
    public const DEAL_TYPES = ['compra'=>'Compra', 'aluguel'=>'Aluguel', 'compra_aluguel'=>'Compra e aluguel'];

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
        if ($this->positiveInt($input['responsible_user_id'] ?? null) === null) $errors['responsible_user_id'] = 'Selecione o responsável.';
        if (!isset(self::MATERIALS[$this->text($input, 'material')])) $errors['material'] = 'Selecione um material válido.';
        foreach (['height_cm'=>'altura', 'length_cm'=>'comprimento'] as $field=>$label) {
            $value = $this->decimal($input[$field] ?? null);
            if ($value === null || $value <= 0 || $value > 100000) $errors[$field] = 'Informe '.$label.' em centímetros, maior que zero.';
        }
        if (!isset(self::DEAL_TYPES[$this->text($input, 'deal_type')])) $errors['deal_type'] = 'Selecione compra, aluguel ou compra e aluguel.';
        if (mb_strlen($this->text($input, 'confirmation_notes')) > 500) $errors['confirmation_notes'] = 'A observação de confirmação deve ter até 500 caracteres.';
        return $errors;
    }

    public function save(array $input, int $actorId, ?int $id = null): array
    {
        $errors = $this->validate($input);
        if ($errors) return ['success'=>false, 'errors'=>$errors];
        $responsibleId = $this->positiveInt($input['responsible_user_id']);
        if (!$this->repository->validResponsible($responsibleId)) return ['success'=>false, 'errors'=>['general'=>'Selecione um responsável ativo.']];
        $data = [':property_id'=>null, ':location'=>'', ':responsible_user_id'=>$responsibleId, ':expires_at'=>null, ':notes'=>null,
            ':material'=>$this->text($input, 'material'), ':height_cm'=>$this->decimal($input['height_cm'] ?? null), ':length_cm'=>$this->decimal($input['length_cm'] ?? null), ':deal_type'=>$this->text($input, 'deal_type'), ':price'=>0];
        $normalized = ['responsible_user_id'=>$responsibleId,
            'material'=>$this->text($input, 'material'), 'height_cm'=>$this->decimal($input['height_cm'] ?? null), 'length_cm'=>$this->decimal($input['length_cm'] ?? null), 'deal_type'=>$this->text($input, 'deal_type')];
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
        if ($row['property_id'] === null) return ['success'=>false,'error'=>'Coloque a placa em um imóvel antes de confirmar a instalação.'];
        if ($row['installed_at'] !== null) return ['success'=>false,'error'=>'Esta placa já foi instalada.'];
        if (!$this->validDate($date) || $date > date('Y-m-d')) return ['success'=>false,'error'=>'Informe uma data de instalação válida, não futura.'];
        $notes=trim($notes);
        if ($notes === '') return ['success'=>false,'error'=>'Confirme a instalação com uma observação.'];
        if (mb_strlen($notes)>1000) return ['success'=>false,'error'=>'A confirmação deve ter até 1.000 caracteres.'];
        $fromStage = (string)$row['board_stage'];
        $installed = Connection::transaction(function () use ($id,$date,$notes,$actorId,$fromStage): bool {
            if (!$this->repository->markInstalled($id,$actorId,$date,$notes,$fromStage)) return false;
            AuditService::log('signboard.install','signboard',(string)$id,null,['installed_at'=>$date,'confirmation'=>$notes],$actorId);
            if ($fromStage !== 'usadas') AuditService::log('signboard.stage_change','signboard',(string)$id,['board_stage'=>$fromStage],['board_stage'=>'usadas','reason'=>'installed'],$actorId);
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

    public function changeStage(int $id, string $stage, int $actorId): array
    {
        if (!isset(self::BOARD_STAGES[$stage])) return ['success'=>false, 'error'=>'Selecione uma etapa válida.'];
        $row = $this->repository->find($id);
        if (!$row) return ['success'=>false, 'error'=>'Placa não encontrada.'];
        if ($stage === 'usadas' && $row['property_id'] === null) return ['success'=>false, 'error'=>'Para usar a placa, primeiro mova para Disponível e selecione um imóvel em “Colocar placa”.'];
        if ($row['board_stage'] === $stage) return ['success'=>true, 'changed'=>false];
        $changed = Connection::transaction(function () use ($id, $stage, $actorId, $row): bool {
            if (!$this->repository->changeStage($id, (string)$row['board_stage'], $stage, $actorId)) return false;
            AuditService::log('signboard.stage_change', 'signboard', (string)$id, ['board_stage'=>$row['board_stage']], ['board_stage'=>$stage], $actorId);
            return true;
        });
        if (!$changed) return ['success'=>false, 'error'=>'A etapa foi alterada por outra pessoa. Atualize o quadro e tente novamente.'];
        return ['success'=>true, 'changed'=>true];
    }

    public function assignToProperty(int $id, mixed $propertyValue, int $actorId): array
    {
        $propertyId = $this->positiveInt($propertyValue);
        if ($propertyId === null || !$this->repository->propertyExists($propertyId)) return ['success'=>false, 'error'=>'Selecione um imóvel cadastrado.'];
        $row = $this->repository->find($id);
        if (!$row) return ['success'=>false, 'error'=>'Placa não encontrada.'];
        if ($row['board_stage'] !== 'disponivel' || $row['property_id'] !== null) return ['success'=>false, 'error'=>'A placa precisa estar Disponível e sem imóvel vinculado para ser colocada.'];
        $assigned = Connection::transaction(function () use ($id, $propertyId, $actorId): bool {
            if (!$this->repository->assignToProperty($id, $propertyId, $actorId)) return false;
            AuditService::log('signboard.assign_to_property', 'signboard', (string)$id, ['property_id'=>null,'board_stage'=>'disponivel'], ['property_id'=>$propertyId,'board_stage'=>'usadas'], $actorId);
            return true;
        });
        if (!$assigned) return ['success'=>false, 'error'=>'A placa mudou desde que você abriu a tela. Atualize o quadro e tente novamente.'];
        return ['success'=>true];
    }

    public function archive(int $id, int $actorId): array
    {
        $row = $this->repository->find($id);
        if (!$row) return ['success'=>false, 'error'=>'Placa não encontrada ou já excluída.'];
        $archived = Connection::transaction(function () use ($id, $actorId, $row): bool {
            if (!$this->repository->archive($id, $actorId)) return false;
            AuditService::log('signboard.delete', 'signboard', (string)$id, [
                'property_id'=>$row['property_id'],
                'property_code'=>$row['property_code'],
                'material'=>$row['material'],
                'board_stage'=>$row['board_stage'],
            ], ['deleted'=>true], $actorId);
            return true;
        });
        if (!$archived) return ['success'=>false, 'error'=>'A placa já foi excluída. Atualize o quadro.'];
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

    private function decimal(mixed $value): ?float
    {
        if (!is_string($value) && !is_numeric($value)) return null;
        $normalized = str_replace(',', '.', trim((string)$value));
        return $normalized !== '' && is_numeric($normalized) ? (float)$normalized : null;
    }
}
