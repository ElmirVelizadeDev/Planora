<?php

namespace App\Services\Backups;

use App\Models\User;
use App\Services\Api\V1\Admin\UserActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupManager
{
    private int $lockDepth = 0;
    private mixed $operationLock = null;
    public function __construct(
        private readonly MysqlDatabaseBackup $databaseBackup,
        private readonly MysqlDatabaseRestore $databaseRestore,
        private readonly ApplicationFilesBackup $filesBackup,
        private readonly BackupCipher $cipher,
        private readonly UserActivityLogger $activityLogger,
    ) {
    }

    public function create(string $type, ?User $actor = null, bool $scheduled = false): array
    {
        return $this->withOperationLock(fn () => $this->createUnlocked($type, $actor, $scheduled));
    }

    private function createUnlocked(string $type, ?User $actor = null, bool $scheduled = false): array
    {
        if (! in_array($type, ['database', 'files', 'full'], true)) throw new RuntimeException('Select a supported backup type.');
        $id = (string) Str::uuid();
        $work = $this->workPath($id);
        $record = [
            'id' => $id,
            'type' => $type,
            'filename' => $this->filename($type),
            'status' => 'running',
            'created_at' => now()->toIso8601String(),
            'completed_at' => null,
            'size_bytes' => 0,
            'checksum' => null,
            'verified' => false,
            'storage_label' => $this->storageLabel(),
            'artifact_key' => $this->artifactKey($id),
            'created_by' => $actor?->id,
            'scheduled' => $scheduled,
            'failure_code' => null,
            'components' => [],
        ];

        try {
            $this->writeRecord($record);
            $this->logOperation('backup.started', $record, $actor);
            $this->activityLogger->record('backup.started', 'An administrator started an application backup.', actor: $actor, context: ['backup_id' => $id, 'backup_type' => $type, 'scheduled' => $scheduled], module: 'Backups');
            $this->prepareWorkDirectory($work);
            $plainArchive = $work.DIRECTORY_SEPARATOR.'backup.zip';
            $record['components'] = $this->buildArchive($type, $work, $plainArchive);
            $this->verifyArchive($plainArchive, $id, $type);

            $encrypted = $work.DIRECTORY_SEPARATOR.'backup.planora';
            $this->cipher->encrypt($plainArchive, $encrypted, $id);
            $this->storeArtifact($encrypted, $record['artifact_key']);
            $record['size_bytes'] = filesize($encrypted) ?: 0;
            $record['checksum'] = hash_file('sha256', $encrypted);
            $record['status'] = 'successful';
            $record['verified'] = true;
            $record['completed_at'] = now()->toIso8601String();
            $this->writeRecord($record);
            $this->logOperation('backup.completed', $record, $actor);
            $this->activityLogger->record('backup.completed', 'An application backup completed and passed integrity verification.', actor: $actor, context: ['backup_id' => $id, 'backup_type' => $type, 'size_bytes' => $record['size_bytes']], module: 'Backups');

            return $record;
        } catch (Throwable $exception) {
            Storage::disk($this->disk())->delete($record['artifact_key']);
            $record['status'] = 'failed';
            $record['failure_code'] = 'backup_failed';
            $record['completed_at'] = now()->toIso8601String();
            $record['verified'] = false;
            $this->writeRecord($record);
            Log::error('Planora backup failed.', ['backup_id' => $id, 'backup_type' => $type, 'exception' => get_class($exception)]);
            $this->logOperation('backup.failed', $record, $actor);
            $this->activityLogger->record('backup.failed', 'An application backup failed. Review the server log for diagnostic details.', actor: $actor, context: ['backup_id' => $id, 'backup_type' => $type], status: 'failure', module: 'Backups');
            throw new RuntimeException('Backup failed. Check the server logs for the operation ID.', 0, $exception);
        } finally {
            $this->removeWorkDirectory($work);
        }
    }

    public function paginate(array $filters): array
    {
        $records = $this->records();
        $query = trim((string) ($filters['search'] ?? ''));
        if ($query !== '') {
            $records = array_values(array_filter($records, fn ($record) => str_contains(mb_strtolower($record['id'].' '.$record['type'].' '.$record['status']), mb_strtolower($query))));
        }
        if (! empty($filters['type'])) $records = array_values(array_filter($records, fn ($record) => $record['type'] === $filters['type']));
        if (! empty($filters['status'])) $records = array_values(array_filter($records, fn ($record) => $record['status'] === $filters['status']));
        $total = count($records);
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return [
            'items' => array_slice($records, ($page - 1) * $perPage, $perPage),
            'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($total / $perPage)), 'per_page' => $perPage, 'total' => $total],
        ];
    }

    public function summary(): array
    {
        $records = $this->records();
        $successful = array_values(array_filter($records, fn ($record) => $record['status'] === 'successful'));
        $failed = array_values(array_filter($records, fn ($record) => $record['status'] === 'failed'));

        return [
            'total_backups' => count($records),
            'latest_successful' => $successful[0] ?? null,
            'latest_failed' => $failed[0] ?? null,
            'storage_bytes' => array_sum(array_column($successful, 'size_bytes')),
            'last_status' => $records[0]['status'] ?? 'none',
        ];
    }

    public function find(string $id): array
    {
        if (! Str::isUuid($id)) throw new RuntimeException('Backup was not found.');
        $contents = Storage::disk($this->disk())->get($this->recordKey($id));
        $record = json_decode($contents, true);
        if (! is_array($record) || ($record['id'] ?? null) !== $id
            || ($record['artifact_key'] ?? null) !== $this->artifactKey($id)
            || ! in_array($record['type'] ?? null, ['database', 'files', 'full'], true)
            || ! in_array($record['status'] ?? null, ['running', 'successful', 'failed'], true)
            || ! is_bool($record['verified'] ?? null)) {
            throw new RuntimeException('Backup was not found.');
        }

        return $record;
    }

    public function downloadPath(string $id): array
    {
        $record = $this->find($id);
        $plain = $this->verifiedPlainArchive($record);
        return ['record' => $record, 'path' => $plain];
    }

    public function delete(string $id, User $actor): void
    {
        $this->withOperationLock(fn () => $this->deleteUnlocked($id, $actor));
    }

    private function deleteUnlocked(string $id, User $actor): void
    {
        $record = $this->find($id);
        if ($record['status'] === 'successful') {
            $valid = array_filter($this->records(), fn ($item) => $item['status'] === 'successful' && $item['verified'] && $item['id'] !== $id);
            if (count($valid) === 0) throw new RuntimeException('The only verified backup cannot be deleted.');
        }

        Storage::disk($this->disk())->delete($record['artifact_key']);
        Storage::disk($this->disk())->delete($this->recordKey($id));
        $this->logOperation('backup.deleted', $record, $actor);
        $this->activityLogger->record('backup.deleted', 'An administrator deleted an application backup.', actor: $actor, context: ['backup_id' => $id, 'backup_type' => $record['type']], module: 'Backups');
    }

    public function restore(string $id, User $actor): array
    {
        return $this->withOperationLock(fn () => $this->restoreUnlocked($id, $actor));
    }

    private function restoreUnlocked(string $id, User $actor): array
    {
        $record = $this->find($id);
        if ($record['status'] !== 'successful' || ! $record['verified']) throw new RuntimeException('Only verified successful backups can be restored.');
        $this->logOperation('restore.started', $record, $actor);
        $this->activityLogger->record('backup.restore_started', 'An administrator started a protected recovery operation.', actor: $actor, context: ['backup_id' => $id, 'backup_type' => $record['type']], module: 'Backups');

        $safety = null;
        $work = $this->workPath('restore-'.Str::uuid());
        try {
            $safety = $this->create('full', $actor);
            $this->prepareWorkDirectory($work);
            $plain = $this->verifiedPlainArchive($record, $work.DIRECTORY_SEPARATOR.'selected.zip');
            $components = $this->extractComponents($plain, $work, $id, $record['type']);
            $restored = [];

            if (isset($components['database'])) $restored['database'] = $this->databaseRestore->restore($components['database']);
            if (isset($components['files'])) $restored['files'] = $this->filesBackup->restore($components['files'], $work.DIRECTORY_SEPARATOR.'restored-files');

            $this->logOperation('restore.completed', $record, $actor);
            $this->activityLogger->record('backup.restore_completed', 'An administrator completed a verified recovery operation.', actor: $actor, context: ['backup_id' => $id, 'backup_type' => $record['type'], 'safety_backup_id' => $safety['id']], module: 'Backups');
            return ['backup_id' => $id, 'type' => $record['type'], 'results' => $restored, 'safety_backup_id' => $safety['id']];
        } catch (Throwable $exception) {
            $rollbackSucceeded = false;
            if ($safety) {
                try {
                    $this->restoreComponentsFromRecord($safety, $work);
                    $rollbackSucceeded = true;
                } catch (Throwable $rollbackException) {
                    Log::critical('Planora automatic recovery from the safety backup failed.', ['backup_id' => $id, 'exception' => get_class($rollbackException)]);
                }
            }
            Log::error('Planora restore failed.', ['backup_id' => $id, 'exception' => get_class($exception), 'safety_restore_succeeded' => $rollbackSucceeded]);
            $this->logOperation('restore.failed', $record, $actor);
            $this->activityLogger->record('backup.restore_failed', 'An administrator recovery operation failed. Review the server log and safety backup.', actor: $actor, context: ['backup_id' => $id, 'safety_backup_id' => $safety['id'] ?? null, 'safety_restore_succeeded' => $rollbackSucceeded], status: 'failure', module: 'Backups');
            throw new RuntimeException($rollbackSucceeded
                ? 'Restore failed. The pre-restore safety backup was reapplied.'
                : 'Restore failed. Preserve the safety backup and review the server logs before further recovery.');
        } finally {
            $this->removeWorkDirectory($work);
        }
    }

    public function prune(): array
    {
        return $this->withOperationLock(fn () => $this->pruneUnlocked());
    }

    private function pruneUnlocked(): array
    {
        $records = $this->records();
        foreach ($records as $record) {
            if ($record['status'] === 'running' && Carbon::parse($record['created_at'])->lt(now()->subDay())) {
                $record['status'] = 'failed';
                $record['failure_code'] = 'interrupted';
                $record['completed_at'] = now()->toIso8601String();
                $this->writeRecord($record);
                $this->logOperation('backup.failed', $record, null);
            }
        }
        $successful = array_values(array_filter($records, fn ($record) => $record['status'] === 'successful' && $record['verified']));
        $keep = [];
        $this->keepLatest($successful, now()->subDays((int) config('backups.retention.daily_days')), 'Y-m-d', $keep);
        $this->keepLatest($successful, now()->subWeeks((int) config('backups.retention.weekly_weeks')), 'o-W', $keep);
        $this->keepLatest($successful, now()->subMonths((int) config('backups.retention.monthly_months')), 'Y-m', $keep);
        if ($successful) $keep[$successful[0]['id']] = true;

        $removed = 0;
        foreach ($successful as $record) {
            if (isset($keep[$record['id']])) continue;
            Storage::disk($this->disk())->delete($record['artifact_key']);
            Storage::disk($this->disk())->delete($this->recordKey($record['id']));
            Log::info('Planora expired backup removed by retention.', ['backup_id' => $record['id'], 'backup_type' => $record['type']]);
            $removed++;
        }

        $workRoot = config('backups.work_path');
        if (is_dir($workRoot)) {
            foreach (new \DirectoryIterator($workRoot) as $entry) {
                if ($entry->isDot() || ! $entry->isDir() || $entry->getMTime() >= now()->subDay()->timestamp) continue;
                $this->removeWorkDirectory($entry->getPathname());
            }
        }

        return ['removed' => $removed, 'retained' => count($keep)];
    }

    private function buildArchive(string $type, string $work, string $archivePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Backup archive could not be created.');
        $components = [];
        try {
            if (in_array($type, ['database', 'full'], true)) {
                $dump = $work.DIRECTORY_SEPARATOR.'database.jsonl.gz';
                $components['database'] = $this->databaseBackup->export($dump);
                if (! $zip->addFile($dump, 'database.jsonl.gz')) throw new RuntimeException('Database export could not be added to the archive.');
            }
            if (in_array($type, ['files', 'full'], true)) {
                $files = $work.DIRECTORY_SEPARATOR.'files.zip';
                $components['files'] = $this->filesBackup->create($files);
                if (! $zip->addFile($files, 'files.zip')) throw new RuntimeException('Application files could not be added to the archive.');
            }
            $zip->addFromString('manifest.json', json_encode([
                'format' => 1, 'type' => $type, 'created_at' => now()->toIso8601String(),
                'components' => $components,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($archivePath);
            throw $exception;
        }
        if (! $zip->close()) throw new RuntimeException('Backup archive did not complete.');

        return $components;
    }

    private function verifyArchive(string $path, string $id, string $type): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Backup archive integrity verification failed.');
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $expected = match ($type) {
            'database' => ['database.jsonl.gz', 'manifest.json'],
            'files' => ['files.zip', 'manifest.json'],
            default => ['database.jsonl.gz', 'files.zip', 'manifest.json'],
        };
        $actual = [];
        for ($index = 0; $index < $zip->numFiles; $index++) $actual[] = $zip->getNameIndex($index);
        sort($actual);
        sort($expected);
        if ($actual !== $expected || ($manifest['format'] ?? null) !== 1 || ($manifest['type'] ?? null) !== $type) {
            $zip->close();
            throw new RuntimeException('Backup archive manifest verification failed.');
        }

        foreach (['database.jsonl.gz' => 'database', 'files.zip' => 'files'] as $name => $component) {
            if ($zip->locateName($name) === false) continue;
            $target = storage_path('app/private/backup-work/verify-'.$id.'-'.$name);
            $parent = dirname($target);
            if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
                $zip->close();
                throw new RuntimeException('Backup integrity verification could not prepare temporary storage.');
            }
            if (! copy('zip://'.$path.'#'.$name, $target)) {
                $zip->close();
                throw new RuntimeException('Backup integrity verification could not read a component.');
            }
            try {
                if ($component === 'database') $this->databaseBackup->inspectCompressedDump($target);
                else $this->filesBackup->verify($target);
            } finally {
                @unlink($target);
            }
        }
        $zip->close();
    }

    private function verifiedPlainArchive(array $record, ?string $destination = null): string
    {
        $disk = Storage::disk($this->disk());
        $stream = $disk->readStream($record['artifact_key']);
        if (! is_resource($stream)) throw new RuntimeException('Backup artifact is unavailable.');
        $ownsWorkingDirectory = $destination === null;
        $workingDirectory = $ownsWorkingDirectory ? $this->workPath('download-'.Str::uuid()) : dirname($destination);
        $encrypted = $workingDirectory.DIRECTORY_SEPARATOR.'encrypted.planora';
        $destination ??= $workingDirectory.DIRECTORY_SEPARATOR.'backup.zip';
        $out = null;
        try {
            $this->prepareWorkDirectory($workingDirectory);
            $out = fopen($encrypted, 'wb');
            if (! is_resource($out)) throw new RuntimeException('Backup could not be verified.');
            if (stream_copy_to_stream($stream, $out) === false) throw new RuntimeException('Backup artifact could not be read completely.');
            fclose($stream);
            $stream = null;
            fclose($out);
            $out = null;

            if (! hash_equals((string) $record['checksum'], hash_file('sha256', $encrypted))) {
                throw new RuntimeException('Backup checksum verification failed.');
            }
            $this->cipher->decrypt($encrypted, $destination, $record['id']);
            $this->verifyArchiveForRestore($destination, $record['id'], $record['type']);
        } catch (Throwable $exception) {
            if (is_resource($stream)) fclose($stream);
            if (is_resource($out)) fclose($out);
            @unlink($encrypted);
            @unlink($destination);
            if ($ownsWorkingDirectory) @rmdir($workingDirectory);
            throw $exception;
        }
        @unlink($encrypted);

        return $destination;
    }

    private function verifyArchiveForRestore(string $path, string $id, string $type): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Backup archive integrity validation failed.');
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (($manifest['format'] ?? null) !== 1 || ($manifest['type'] ?? null) !== $type) {
            $zip->close();
            throw new RuntimeException('Backup archive manifest is invalid.');
        }
        $zip->close();
    }

    private function extractComponents(string $archive, string $work, string $id, string $type): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Backup archive is corrupted.');
        $components = [];
        try {
            foreach (['database.jsonl.gz' => 'database', 'files.zip' => 'files'] as $entry => $name) {
                if ($zip->locateName($entry) === false) continue;
                $target = $work.DIRECTORY_SEPARATOR.$entry;
                $stream = $zip->getStream($entry);
                if (! is_resource($stream)) throw new RuntimeException('A backup component could not be opened.');
                $output = fopen($target, 'wb');
                if (! is_resource($output)) throw new RuntimeException('A backup component could not be staged.');
                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                if ($name === 'database') $this->databaseBackup->inspectCompressedDump($target);
                else $this->filesBackup->verify($target);
                $components[$name] = $target;
            }
        } finally {
            $zip->close();
        }
        $required = match ($type) { 'database' => ['database'], 'files' => ['files'], default => ['database', 'files'] };
        if (array_diff($required, array_keys($components))) throw new RuntimeException('Backup is missing a required recovery component.');

        return $components;
    }

    private function restoreComponentsFromRecord(array $record, string $work): void
    {
        $archive = $this->verifiedPlainArchive($record, $work.DIRECTORY_SEPARATOR.'safety.zip');
        $components = $this->extractComponents($archive, $work, $record['id'], $record['type']);
        if (isset($components['database'])) $this->databaseRestore->restore($components['database']);
        if (isset($components['files'])) $this->filesBackup->restore($components['files'], $work.DIRECTORY_SEPARATOR.'safety-files');
    }

    private function keepLatest(array $records, Carbon $since, string $keyFormat, array &$keep): void
    {
        $groups = [];
        foreach ($records as $record) {
            $date = Carbon::parse($record['created_at']);
            if ($date->lt($since)) continue;
            $key = $date->format($keyFormat);
            if (! isset($groups[$key]) || $record['created_at'] > $groups[$key]['created_at']) $groups[$key] = $record;
        }
        foreach ($groups as $record) $keep[$record['id']] = true;
    }

    private function records(): array
    {
        $disk = Storage::disk($this->disk());
        $records = [];
        foreach ($disk->allFiles($this->configPath('records')) as $key) {
            try {
                $record = json_decode($disk->get($key), true);
                if (is_array($record) && Str::isUuid($record['id'] ?? null) && ($record['artifact_key'] ?? null) === $this->artifactKey($record['id'])) $records[] = $record;
            } catch (Throwable) {
                continue;
            }
        }
        usort($records, fn ($left, $right) => strcmp($right['created_at'] ?? '', $left['created_at'] ?? ''));
        return $records;
    }

    private function writeRecord(array $record): void
    {
        if (! Storage::disk($this->disk())->put($this->recordKey($record['id']), json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Backup metadata could not be written to private storage.');
        }
    }

    private function storeArtifact(string $localPath, string $key): void
    {
        $stream = fopen($localPath, 'rb');
        if (! is_resource($stream)) throw new RuntimeException('Encrypted backup could not be read.');
        try {
            if (! Storage::disk($this->disk())->put($key, $stream)) throw new RuntimeException('Backup could not be written to private storage.');
        } finally {
            fclose($stream);
        }
    }

    private function logOperation(string $event, array $record, ?User $actor): void
    {
        Log::info('Planora backup operation.', [
            'event' => $event,
            'backup_id' => $record['id'],
            'backup_type' => $record['type'],
            'actor_id' => $actor?->id,
            'status' => $record['status'],
        ]);
    }

    private function storageLabel(): string
    {
        $driver = config('filesystems.disks.'.$this->disk().'.driver');
        return $driver === 'local' ? 'Local private storage' : 'External private storage';
    }

    public function recordDownload(array $record, User $actor): void
    {
        $this->logOperation('backup.downloaded', $record, $actor);
        $this->activityLogger->record('backup.downloaded', 'An administrator downloaded an encrypted application backup.', actor: $actor, context: ['backup_id' => $record['id'], 'backup_type' => $record['type']], module: 'Backups');
    }

    private function withOperationLock(callable $callback): mixed
    {
        if ($this->lockDepth > 0) {
            $this->lockDepth++;
            try { return $callback(); } finally { $this->lockDepth--; }
        }

        $lock = Cache::lock('planora:backup-management', 86400);
        if (! $lock->get()) throw new RuntimeException('Another backup or recovery operation is already running.');
        $this->operationLock = $lock;
        $this->lockDepth = 1;
        try {
            return $callback();
        } finally {
            $this->lockDepth = 0;
            $this->operationLock?->release();
            $this->operationLock = null;
        }
    }

    private function disk(): string
    {
        $name = (string) config('backups.disk');
        if ($name === '' || $name === 'public' || ! config("filesystems.disks.{$name}")) {
            throw new RuntimeException('Backup storage must use a configured private filesystem disk.');
        }
        $diskConfig = config("filesystems.disks.{$name}");
        if (($diskConfig['visibility'] ?? 'private') === 'public') throw new RuntimeException('Public backup storage is not allowed.');
        if (($diskConfig['driver'] ?? null) === 'local') {
            $root = $this->normalizePath((string) ($diskConfig['root'] ?? ''));
            $public = $this->normalizePath(public_path());
            if ($root === '' || $root === $public || str_starts_with($root, rtrim($public, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Backup storage must be outside the public web directory.');
            }
        }

        return $name;
    }

    private function configPath(string $suffix): string
    {
        $path = trim((string) config('backups.path'), '/');
        if ($path === '' || str_contains($path, '\\') || preg_match('/^[A-Za-z]:/', $path)
            || in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true)) {
            throw new RuntimeException('Backup storage path is invalid.');
        }
        return $path.'/'.$suffix;
    }
    private function recordKey(string $id): string { return $this->configPath('records/'.$id.'.json'); }
    private function artifactKey(string $id): string { return $this->configPath('artifacts/'.$id.'.planora'); }
    private function workPath(string $name): string { return config('backups.work_path').DIRECTORY_SEPARATOR.$name; }
    private function filename(string $type): string { return 'planora-'.$type.'-'.now()->format('Y-m-d-His').'.zip.enc'; }

    private function normalizePath(string $path): string
    {
        if ($path === '') return '';
        $resolved = realpath($path);
        if ($resolved !== false) return rtrim($resolved, DIRECTORY_SEPARATOR);
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path
            : base_path($path);
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolute), DIRECTORY_SEPARATOR);
    }

    private function prepareWorkDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) throw new RuntimeException('Private backup working storage could not be prepared.');
    }

    private function removeWorkDirectory(string $path): void
    {
        if (! is_dir($path)) return;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($path);
    }
}
