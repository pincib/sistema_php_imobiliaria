<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Audit\AuditService;
use App\Backup\BackupService;
use App\Database\Connection;
use PDO;
use RuntimeException;

final class ImportCleanupService
{
    private PDO $pdo;
    private BackupService $backups;

    public function __construct(?PDO $pdo = null, ?BackupService $backups = null)
    {
        $this->pdo = $pdo ?? Connection::getPdo();
        $this->backups = $backups ?? new BackupService();
    }

    public function preview(string $kind): array
    {
        $this->assertKind($kind);
        $trackedSql = $this->trackedEntitiesSql($kind);
        $legacySql = $this->legacyEntitiesSql($kind);
        $observedLegacySql = $this->observedLegacyEntitiesSql($kind);
        $importedSql = $this->importedEntitiesSql($kind);
        $tracked = (int)$this->pdo->query("SELECT COUNT(*) FROM ({$trackedSql}) imported")->fetchColumn();
        $legacy = (int)$this->pdo->query("SELECT COUNT(*) FROM ({$observedLegacySql}) imported")->fetchColumn();
        $legacyRemovable = (int)$this->pdo->query("SELECT COUNT(*) FROM ({$legacySql}) imported")->fetchColumn();
        $batches = $this->pdo->prepare("SELECT COUNT(*) FROM import_batches WHERE data_kind=:kind AND status='completed'");
        $batches->execute([':kind' => $kind]);
        $relations = match ($kind) {
            'leads' => (int)$this->pdo->query("SELECT COUNT(*) FROM lead_properties WHERE lead_id IN ({$importedSql})")->fetchColumn()
                + (int)$this->pdo->query("SELECT COUNT(*) FROM lead_interactions WHERE lead_id IN ({$importedSql})")->fetchColumn()
                + (int)$this->pdo->query("SELECT COUNT(*) FROM lead_follow_ups WHERE lead_id IN ({$importedSql})")->fetchColumn()
                + (int)$this->pdo->query("SELECT COUNT(*) FROM lead_assignments WHERE lead_id IN ({$importedSql})")->fetchColumn()
                + (int)$this->pdo->query("SELECT COUNT(*) FROM whatsapp_contact_logs WHERE contact_type='lead' AND contact_id IN ({$importedSql})")->fetchColumn(),
            'owners' => (int)$this->pdo->query("SELECT COUNT(*) FROM owner_properties WHERE owner_id IN ({$importedSql})")->fetchColumn()
                + (int)$this->pdo->query("SELECT COUNT(*) FROM whatsapp_contact_logs WHERE contact_type='owner' AND contact_id IN ({$importedSql})")->fetchColumn(),
            default => (int)$this->pdo->query("SELECT COUNT(*) FROM lead_properties WHERE property_id IN ({$importedSql})")->fetchColumn()
                + (int)$this->pdo->query("SELECT COUNT(*) FROM owner_properties WHERE property_id IN ({$importedSql})")->fetchColumn(),
        };
        $signboards = $kind === 'properties'
            ? (int)$this->pdo->query("SELECT COUNT(*) FROM signboards WHERE property_id IN ({$importedSql})")->fetchColumn()
            : 0;
        $removable = $tracked + $legacyRemovable;
        $total = $tracked + $legacy;
        return ['total' => $total, 'removable' => $removable, 'preserved' => max(0, $total - $removable), 'relations' => $relations, 'legacy' => $legacy, 'legacy_removable' => $legacyRemovable, 'signboards' => $signboards, 'batches' => (int)$batches->fetchColumn()];
    }

    public function clear(string $kind, int $actorId): array
    {
        $this->assertKind($kind);
        $preview = $this->preview($kind);
        $filename = sprintf('pre_import_clear_%s_%s_%s.sqlite', $kind, date('Ymd_His'), bin2hex(random_bytes(3)));
        $backup = $this->backups->createBackup($filename, autoRotate: false);

        $result = Connection::transaction(function (PDO $pdo) use ($kind, $actorId): array {
            $before = $this->preview($kind);
            $trackedBatches = $this->integerIds($pdo->query($this->trackedBatchesSql($kind))->fetchAll(PDO::FETCH_COLUMN));
            $legacyBatches = $this->integerIds($pdo->query($this->legacyBatchesSql($kind))->fetchAll(PDO::FETCH_COLUMN));
            $importedEntities = $this->importedEntitiesSql($kind);

            if ($kind === 'properties') {
                $detach = $pdo->prepare("UPDATE signboards SET property_id=NULL,updated_at=CURRENT_TIMESTAMP WHERE property_id IN ({$importedEntities})");
                $detach->execute();
                $signboardsUnlinked = $detach->rowCount();
            } else {
                $contactType = $kind === 'leads' ? 'lead' : 'owner';
                $logs = $pdo->prepare("DELETE FROM whatsapp_contact_logs WHERE contact_type=:type AND contact_id IN ({$importedEntities})");
                $logs->execute([':type' => $contactType]);
                $signboardsUnlinked = 0;
            }

            $delete = $pdo->prepare("DELETE FROM {$kind} WHERE id IN ({$importedEntities})");
            $delete->execute();
            $deleted = $delete->rowCount();

            $trackedBatchList = implode(',', $trackedBatches ?: [0]);
            $legacyBatchList = implode(',', $legacyBatches ?: [0]);
            if ($trackedBatches) {
                $pdo->exec("DELETE FROM import_source_rows WHERE batch_id IN ({$trackedBatchList})");
                $pdo->exec("UPDATE {$kind} SET import_batch_id=NULL WHERE import_batch_id IN ({$trackedBatchList})");
                $pdo->exec("UPDATE import_batches SET cleanup_supported=0,cleaned_at=CURRENT_TIMESTAMP,status='cleaned' WHERE id IN ({$trackedBatchList})");
            }
            if ($legacyBatches) {
                $pdo->exec("DELETE FROM import_source_rows WHERE batch_id IN ({$legacyBatchList}) AND entity_type='" . $this->sourceKind($kind) . "'");
                $pdo->exec("UPDATE import_batches SET cleaned_at=CURRENT_TIMESTAMP,status='cleaned' WHERE id IN ({$legacyBatchList}) AND NOT EXISTS (SELECT 1 FROM import_source_rows s WHERE s.batch_id=import_batches.id)");
            }

            $preserved = max(0, $before['total'] - $deleted);
            AuditService::log('csv.import.cleanup', 'import_batch', $kind, null, ['deleted' => $deleted, 'preserved' => $preserved, 'links_deleted' => $before['relations'], 'signboards_unlinked' => $signboardsUnlinked, 'dataset' => $kind], $actorId);
            return ['deleted' => $deleted, 'preserved' => $preserved, 'links_deleted' => $before['relations'], 'signboards_unlinked' => $signboardsUnlinked];
        });

        return $result + ['backup' => $backup['filename'], 'preview' => $preview];
    }

    private function sourceKind(string $kind): string
    {
        return match ($kind) { 'leads' => 'lead', 'owners' => 'owner', default => 'property' };
    }

    private function trackedBatchesSql(string $kind): string
    {
        return "SELECT id FROM import_batches WHERE cleanup_supported=1 AND data_kind='{$kind}' AND cleaned_at IS NULL";
    }

    private function trackedEntitiesSql(string $kind): string
    {
        return "SELECT e.id FROM {$kind} e JOIN import_batches b ON b.id=e.import_batch_id WHERE b.cleanup_supported=1 AND b.data_kind='{$kind}' AND b.cleaned_at IS NULL";
    }

    /** Returns legacy entities only when batch counts prove the rows were created by that import. */
    private function legacyEntitiesSql(string $kind): string
    {
        $source = $this->sourceKind($kind);
        $fromSource = "SELECT DISTINCT e.id FROM {$kind} e JOIN import_source_rows s ON s.entity_id=e.id AND s.entity_type='{$source}'
            JOIN import_batches b ON b.id=s.batch_id JOIN (SELECT batch_id,COUNT(DISTINCT entity_id) imported_count FROM import_source_rows
                WHERE entity_type='{$source}' AND entity_id IS NOT NULL GROUP BY batch_id) source_counts
                ON source_counts.batch_id=b.id AND source_counts.imported_count=b.created_count
            WHERE b.cleanup_supported=0 AND b.cleaned_at IS NULL AND b.status='completed'";
        return $kind === 'properties' ? "{$fromSource} UNION SELECT p.id FROM properties p JOIN import_batches b ON p.created_at=b.created_at
            WHERE b.cleanup_supported=0 AND b.cleaned_at IS NULL AND b.status='completed' AND b.updated_count=0 AND b.created_count>0
            AND json_extract(b.summary_json,'$.kind')='properties'" : $fromSource;
    }

    private function observedLegacyEntitiesSql(string $kind): string
    {
        $source = $this->sourceKind($kind);
        $fromSource = "SELECT DISTINCT e.id FROM {$kind} e JOIN import_source_rows s ON s.entity_id=e.id AND s.entity_type='{$source}'
            JOIN import_batches b ON b.id=s.batch_id WHERE b.cleanup_supported=0 AND b.cleaned_at IS NULL AND b.status='completed'";
        return $kind === 'properties' ? "{$fromSource} UNION SELECT p.id FROM properties p JOIN import_batches b ON p.created_at=b.created_at
            WHERE b.cleanup_supported=0 AND b.cleaned_at IS NULL AND b.status='completed' AND b.updated_count=0 AND b.created_count>0
            AND json_extract(b.summary_json,'$.kind')='properties'" : $fromSource;
    }

    private function legacyEntitiesBatchesSql(string $kind): string
    {
        $source = $this->sourceKind($kind);
        $fromSource = "SELECT DISTINCT b.id FROM import_batches b JOIN import_source_rows s ON s.batch_id=b.id WHERE s.entity_type='{$source}'
            JOIN (SELECT batch_id,COUNT(DISTINCT entity_id) imported_count FROM import_source_rows WHERE entity_type='{$source}'
                AND entity_id IS NOT NULL GROUP BY batch_id) source_counts ON source_counts.batch_id=b.id AND source_counts.imported_count=b.created_count
            WHERE b.cleanup_supported=0 AND b.cleaned_at IS NULL AND b.status='completed'";
        return $kind === 'properties' ? "{$fromSource} UNION SELECT b.id FROM import_batches b WHERE b.cleanup_supported=0 AND b.cleaned_at IS NULL
            AND b.status='completed' AND b.updated_count=0 AND b.created_count>0 AND json_extract(b.summary_json,'$.kind')='properties'
            AND EXISTS (SELECT 1 FROM properties p WHERE p.created_at=b.created_at)" : $fromSource;
    }

    private function legacyBatchesSql(string $kind): string
    {
        return "SELECT id FROM ({$this->legacyEntitiesBatchesSql($kind)}) legacy_batches";
    }

    private function importedEntitiesSql(string $kind): string
    {
        return $this->trackedEntitiesSql($kind) . ' UNION ' . $this->legacyEntitiesSql($kind);
    }

    /** Captures numeric IDs before cleanup mutates provenance that the selection depends on. */
    private function integerIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function assertKind(string $kind): void
    {
        if (!in_array($kind, ['leads', 'owners', 'properties'], true)) throw new RuntimeException('Tipo de planilha inválido.');
    }
}
