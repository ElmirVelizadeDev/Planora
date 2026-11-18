<?php

namespace Tests\Feature\Admin\Backups;

use App\Services\Api\V1\Admin\UserActivityLogger;
use App\Services\Backups\ApplicationFilesBackup;
use App\Services\Backups\BackupCipher;
use App\Services\Backups\BackupManager;
use App\Services\Backups\MysqlDatabaseBackup;
use App\Services\Backups\MysqlDatabaseRestore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupStorageTest extends TestCase
{
    use DatabaseTransactions;

    private string $workRoot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        Storage::fake('public');
        config(['backups.disk' => 'backups', 'backups.path' => 'test-planora-backups']);
        config(['filesystems.disks.backups.driver' => 'local', 'filesystems.disks.backups.visibility' => 'private']);
        $this->workRoot = storage_path('framework/testing/backup-work-'.uniqid('', true));
        config(['backups.work_path' => $this->workRoot, 'backups.max_archive_bytes' => 10485760]);
        app()->instance(UserActivityLogger::class, Mockery::mock(UserActivityLogger::class)->shouldIgnoreMissing());
        app()->instance(MysqlDatabaseRestore::class, Mockery::mock(MysqlDatabaseRestore::class));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workRoot)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->workRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
            }
            @rmdir($this->workRoot);
        }
        parent::tearDown();
    }

    public function test_database_backup_manager_encrypts_verifies_and_rejects_modified_artifacts(): void
    {
        $this->fakeDatabaseDump();
        $manager = app(BackupManager::class);
        $record = $manager->create('database');

        $this->assertSame('successful', $record['status']);
        $this->assertTrue($record['verified']);
        $files = Storage::disk('backups')->allFiles('test-planora-backups/artifacts');
        $this->assertCount(1, $files);
        $encrypted = Storage::disk('backups')->get($files[0]);
        $this->assertStringStartsWith("PLANORA-BACKUP\x01", $encrypted);
        $this->assertStringNotContainsString('sensitive-row-value', $encrypted);

        $download = $manager->downloadPath($record['id']);
        $this->assertSame($record['id'], $download['record']['id']);
        $this->assertFileExists($download['path']);
        @unlink($download['path']);
        @rmdir(dirname($download['path']));

        Storage::disk('backups')->put($files[0], $encrypted.'tampered');
        try {
            $manager->downloadPath($record['id']);
            $this->fail('Modified backup artifact must fail checksum verification.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('checksum', $exception->getMessage());
        }
        $downloadDirectories = glob($this->workRoot.DIRECTORY_SEPARATOR.'download-*', GLOB_ONLYDIR) ?: [];
        $this->assertSame([], $downloadDirectories);
    }

    public function test_failed_database_backup_is_recorded_without_an_artifact(): void
    {
        $database = Mockery::mock(MysqlDatabaseBackup::class);
        $database->shouldReceive('export')->once()->andThrow(new RuntimeException('private database connection details'));
        $database->shouldNotReceive('inspectCompressedDump');
        app()->instance(MysqlDatabaseBackup::class, $database);

        try {
            app(BackupManager::class)->create('database');
            $this->fail('Failed database backup should not report success.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup failed. Check the server logs for the operation ID.', $exception->getMessage());
        }

        $records = Storage::disk('backups')->allFiles('test-planora-backups/records');
        $this->assertCount(1, $records);
        $record = json_decode(Storage::disk('backups')->get($records[0]), true);
        $this->assertSame('failed', $record['status']);
        $this->assertSame('backup_failed', $record['failure_code']);
        $this->assertStringNotContainsString('private database connection details', json_encode($record));
        $this->assertSame([], Storage::disk('backups')->allFiles('test-planora-backups/artifacts'));
    }

    public function test_backup_cipher_rejects_trailing_bytes_after_the_authenticated_end_marker(): void
    {
        config(['backups.encryption_key' => base64_encode(random_bytes(32))]);
        $source = $this->workRoot.DIRECTORY_SEPARATOR.'plain.bin';
        $encrypted = $this->workRoot.DIRECTORY_SEPARATOR.'encrypted.bin';
        $destination = $this->workRoot.DIRECTORY_SEPARATOR.'decrypted.bin';
        if (! is_dir(dirname($source))) mkdir(dirname($source), 0700, true);
        file_put_contents($source, 'database backup content');

        $cipher = app(BackupCipher::class);
        $cipher->encrypt($source, $encrypted, 'backup-test-id');
        $cipher->decrypt($encrypted, $destination, 'backup-test-id');
        $this->assertSame('database backup content', file_get_contents($destination));

        file_put_contents($encrypted, file_get_contents($encrypted).'unexpected');
        try {
            $cipher->decrypt($encrypted, $destination, 'backup-test-id');
            $this->fail('Cipher must reject trailing bytes after the completion marker.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('trailing data', $exception->getMessage());
        }
    }

    public function test_backup_metadata_cannot_redirect_artifact_reads_outside_its_generated_key(): void
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        Storage::disk('backups')->put('test-planora-backups/records/'.$id.'.json', json_encode([
            'id' => $id,
            'type' => 'database',
            'status' => 'successful',
            'verified' => true,
            'artifact_key' => 'test-planora-backups/records/another-record.json',
        ], JSON_THROW_ON_ERROR));

        try {
            app(BackupManager::class)->find($id);
            $this->fail('Backup metadata must not redirect artifact access to another storage key.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup was not found.', $exception->getMessage());
        }
    }

    public function test_retention_prunes_expired_artifacts_but_keeps_the_only_verified_backup(): void
    {
        config(['backups.retention.daily_days' => 2, 'backups.retention.weekly_weeks' => 1, 'backups.retention.monthly_months' => 1]);
        $ids = [];
        foreach ([90, 45, 10, 1] as $daysAgo) {
            $id = (string) \Illuminate\Support\Str::uuid();
            $ids[] = $id;
            $record = [
                'id' => $id,
                'type' => 'full',
                'status' => 'successful',
                'verified' => true,
                'created_at' => now()->subDays($daysAgo)->toIso8601String(),
                'completed_at' => now()->subDays($daysAgo)->toIso8601String(),
                'size_bytes' => 20,
                'checksum' => str_repeat('a', 64),
                'artifact_key' => 'test-planora-backups/artifacts/'.$id.'.planora',
                'storage_label' => 'Local private storage',
                'failure_code' => null,
                'components' => [],
            ];
            Storage::disk('backups')->put('test-planora-backups/records/'.$id.'.json', json_encode($record, JSON_THROW_ON_ERROR));
            Storage::disk('backups')->put($record['artifact_key'], 'encrypted-artifact');
        }

        $manager = app(BackupManager::class);
        $result = $manager->prune();
        $this->assertSame(3, $result['removed']);
        $this->assertSame(1, count(Storage::disk('backups')->allFiles('test-planora-backups/records')));
        $this->assertFileExists(Storage::disk('backups')->path('test-planora-backups/artifacts/'.$ids[3].'.planora'));

        try {
            $manager->delete($ids[3], User::factory()->create(['role' => 'admin', 'status' => 'active']));
            $this->fail('The only verified backup must not be deletable.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The only verified backup cannot be deleted.', $exception->getMessage());
        }
    }

    public function test_file_backup_preserves_managed_uploads_and_rejects_path_traversal(): void
    {
        Storage::disk('public')->put('user-avatars/owner/avatar.png', 'avatar-bytes');
        $archive = $this->workRoot.DIRECTORY_SEPARATOR.'files.zip';
        if (! is_dir(dirname($archive))) mkdir(dirname($archive), 0700, true);
        $service = app(ApplicationFilesBackup::class);
        $summary = $service->create($archive);
        $verified = $service->verify($archive);
        $this->assertGreaterThanOrEqual(1, $summary['files']);
        $this->assertSame($summary['files'], $verified['files']);
        $this->assertContains('storage/public/user-avatars/owner/avatar.png', array_column($verified['manifest']['files'], 'path'));

        $unsafe = $this->workRoot.DIRECTORY_SEPARATOR.'unsafe.zip';
        $zip = new ZipArchive();
        $zip->open($unsafe, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../../.env', 'never extract');
        $zip->addFromString('manifest.json', json_encode(['format' => 1, 'files' => [['path' => '../../.env', 'bytes' => 13, 'sha256' => hash('sha256', 'never extract')]]]));
        $zip->close();

        try {
            $service->verify($unsafe);
            $this->fail('Archives with traversal paths must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unsafe path', $exception->getMessage());
        }

        $symlink = $this->workRoot.DIRECTORY_SEPARATOR.'symlink.zip';
        $symlinkPath = 'storage/public/user-avatars/owner/link';
        $target = '../outside-avatar.png';
        $zip = new ZipArchive();
        $zip->open($symlink, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($symlinkPath, $target);
        $zip->setExternalAttributesName($symlinkPath, ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->addFromString('manifest.json', json_encode([
            'format' => 1,
            'files' => [['path' => $symlinkPath, 'bytes' => strlen($target), 'sha256' => hash('sha256', $target)]],
        ], JSON_THROW_ON_ERROR));
        $zip->close();

        try {
            $service->verify($symlink);
            $this->fail('Archives containing symbolic links must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unsupported link', $exception->getMessage());
        }
    }

    public function test_file_restore_accepts_an_upload_at_the_first_archive_index(): void
    {
        $contents = 'restored-avatar';
        $path = 'storage/public/user-avatars/member/avatar.png';
        $archive = $this->workRoot.DIRECTORY_SEPARATOR.'first-entry.zip';
        if (! is_dir(dirname($archive))) mkdir(dirname($archive), 0700, true);
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($path, $contents);
        $zip->addFromString('manifest.json', json_encode([
            'format' => 1,
            'files' => [['path' => $path, 'bytes' => strlen($contents), 'sha256' => hash('sha256', $contents)]],
            'total_bytes' => strlen($contents),
        ], JSON_THROW_ON_ERROR));
        $zip->close();

        $service = app(ApplicationFilesBackup::class);
        $result = $service->restore($archive, $this->workRoot.DIRECTORY_SEPARATOR.'restore');
        $this->assertSame(1, $result['files']);
        $this->assertSame($contents, Storage::disk('public')->get('user-avatars/member/avatar.png'));
    }

    private function fakeDatabaseDump(): void
    {
        $database = Mockery::mock(MysqlDatabaseBackup::class);
        $database->shouldReceive('export')->once()->andReturnUsing(function (string $destination): array {
            file_put_contents($destination, gzencode(json_encode(['kind' => 'header', 'format' => 1, 'driver' => 'mysql', 'tables' => ['users']])."\n".json_encode(['kind' => 'table', 'name' => 'users', 'create' => 'CREATE TABLE `users` (`id` bigint)', 'columns' => ['id']])."\n".json_encode(['kind' => 'complete', 'counts' => ['users' => 0]])."\n"));
            return ['tables' => 1, 'rows' => 0, 'counts' => ['users' => 0]];
        });
        $database->shouldReceive('inspectCompressedDump')->atLeast()->once()->andReturn(['tables' => 1, 'names' => ['users']]);
        app()->instance(MysqlDatabaseBackup::class, $database);
    }
}
