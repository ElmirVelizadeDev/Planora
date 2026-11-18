<?php

namespace App\Services\Backups;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ApplicationFilesBackup
{
    private const PREFIXES = ['storage/public/user-avatars/', 'storage/public/settings/branding/'];
    private const MAINTENANCE_FILE = 'storage/framework/planora-maintenance-scopes.json';

    public function create(string $destination): array
    {
        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Application file backup archive could not be created.');
        }

        $files = [];
        $temporaryFiles = [];
        $totalBytes = 0;
        try {
            $disk = Storage::disk('public');
            foreach (['user-avatars', 'settings/branding'] as $directory) {
                foreach ($disk->allFiles($directory) as $path) {
                    $path = $this->safeArchivePath('storage/public/'.ltrim($path, '/'));
                    if (! $path) throw new RuntimeException('A managed upload has an unsafe path and cannot be backed up.');
                    $relative = substr($path, strlen('storage/public/'));
                    $this->assertNoLocalSymlinks('public', $relative);
                    $stream = $disk->readStream($relative);
                    if (! is_resource($stream)) throw new RuntimeException('An uploaded file could not be included in the backup.');
                    $temporary = tempnam(dirname($destination), 'planora-upload-');
                    $output = is_string($temporary) ? fopen($temporary, 'wb') : false;
                    if (! is_resource($output)) {
                        fclose($stream);
                        throw new RuntimeException('Private upload staging could not be prepared.');
                    }
                    $temporaryFiles[] = $temporary;
                    $hash = hash_init('sha256');
                    $bytes = 0;
                    try {
                        while (! feof($stream)) {
                            $chunk = fread($stream, 1048576);
                            if ($chunk === false) throw new RuntimeException('An uploaded file could not be read for backup.');
                            if ($chunk === '') continue;
                            $bytes += strlen($chunk);
                            $totalBytes += strlen($chunk);
                            if ($totalBytes > config('backups.max_archive_bytes')) throw new RuntimeException('The application file archive exceeds its configured size limit.');
                            hash_update($hash, $chunk);
                            $this->writeAll($output, $chunk);
                        }
                    } finally {
                        fclose($stream);
                        fclose($output);
                    }
                    if (! $zip->addFile($temporary, $path)) {
                        throw new RuntimeException('An uploaded file could not be added to the backup.');
                    }
                    $files[] = ['path' => $path, 'bytes' => $bytes, 'sha256' => hash_final($hash)];
                }
            }

            $maintenancePath = storage_path('framework/planora-maintenance-scopes.json');
            if (is_file($maintenancePath) && ! is_link($maintenancePath)) {
                $contents = file_get_contents($maintenancePath);
                if (! is_string($contents)) throw new RuntimeException('Application configuration metadata could not be read.');
                $totalBytes += strlen($contents);
                if (! $zip->addFromString(self::MAINTENANCE_FILE, $contents)) throw new RuntimeException('Application metadata could not be added to the backup.');
                $files[] = ['path' => self::MAINTENANCE_FILE, 'bytes' => strlen($contents), 'sha256' => hash('sha256', $contents)];
            }

            $zip->addFromString('manifest.json', json_encode([
                'format' => 1,
                'files' => $files,
                'total_bytes' => $totalBytes,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            $zip->close();
            foreach ($temporaryFiles as $temporary) @unlink($temporary);
            @unlink($destination);
            throw $exception;
        }

        if (! $zip->close()) {
            foreach ($temporaryFiles as $temporary) @unlink($temporary);
            throw new RuntimeException('Application file backup archive did not complete.');
        }
        foreach ($temporaryFiles as $temporary) @unlink($temporary);
        $this->verify($destination);

        return ['files' => count($files), 'bytes' => $totalBytes];
    }

    public function verify(string $archivePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Application file backup is not a valid archive.');
        $manifest = null;
        $totalBytes = 0;
        $allowedFiles = [];
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if (! is_array($entry)) throw new RuntimeException('Application file backup integrity validation failed.');
                $name = (string) $entry['name'];
                if ($name === 'manifest.json') {
                    $manifest = json_decode((string) $zip->getFromIndex($index), true, 512, JSON_THROW_ON_ERROR);
                    continue;
                }
                if ($name === '' || str_ends_with($name, '/')) continue;
                $safe = $this->safeArchivePath($name);
                if ($safe !== $name) throw new RuntimeException('Application file backup contains an unsafe path.');
                if ($this->isSymlink($zip, $index)) throw new RuntimeException('Application file backup contains an unsupported link.');
                $totalBytes += (int) ($entry['size'] ?? 0);
                if ($totalBytes > config('backups.max_archive_bytes')) throw new RuntimeException('Application file backup exceeds its configured size limit.');
                $allowedFiles[] = $name;
            }
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1 || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('Application file backup manifest is invalid.');
            }
            $manifestPaths = array_column($manifest['files'], 'path');
            sort($allowedFiles);
            sort($manifestPaths);
            if ($allowedFiles !== $manifestPaths) throw new RuntimeException('Application file backup contents do not match its manifest.');
            foreach ($manifest['files'] as $file) {
                if (! is_array($file) || ! is_string($file['path'] ?? null) || ! is_int($file['bytes'] ?? null) || $file['bytes'] < 0
                    || ! is_string($file['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $file['sha256'])) {
                    throw new RuntimeException('Application file backup manifest entry is invalid.');
                }
                $this->verifyEntry($zip, $file);
            }
        } finally {
            $zip->close();
        }

        return ['files' => count($allowedFiles), 'bytes' => $totalBytes, 'manifest' => $manifest];
    }

    public function restore(string $archivePath, string $stagingDirectory): array
    {
        $verified = $this->verify($archivePath);
        if (! is_dir($stagingDirectory) && ! mkdir($stagingDirectory, 0700, true) && ! is_dir($stagingDirectory)) {
            throw new RuntimeException('Application file restore could not prepare its staging area.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Application file backup could not be opened.');
        $restored = 0;
        try {
            foreach ($verified['manifest']['files'] as $file) {
                $path = $this->safeArchivePath($file['path'] ?? null);
                if (! $path || $zip->locateName($path) === false) throw new RuntimeException('Application file backup references a missing file.');
                $stagedPath = $stagingDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
                $parent = dirname($stagedPath);
                if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) throw new RuntimeException('File restore staging failed.');
                $input = $zip->getStream($path);
                $output = fopen($stagedPath, 'wb');
                if (! is_resource($input) || ! is_resource($output)) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
                    throw new RuntimeException('File restore staging failed.');
                }
                $hash = hash_init('sha256');
                $bytes = 0;
                try {
                    while (! feof($input)) {
                        $chunk = fread($input, 1048576);
                        if ($chunk === false) throw new RuntimeException('Application file backup could not be read.');
                        if ($chunk === '') continue;
                        $bytes += strlen($chunk);
                        hash_update($hash, $chunk);
                        $this->writeAll($output, $chunk);
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if ($bytes !== (int) ($file['bytes'] ?? -1) || ! hash_equals((string) $file['sha256'], hash_final($hash))) {
                    @unlink($stagedPath);
                    throw new RuntimeException('Application file backup integrity validation failed.');
                }

                if ($path === self::MAINTENANCE_FILE) {
                    $target = storage_path('framework/planora-maintenance-scopes.json');
                    $targetDirectory = dirname($target);
                    if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0750, true) && ! is_dir($targetDirectory)) throw new RuntimeException('Application metadata could not be restored.');
                    if (! copy($stagedPath, $target)) throw new RuntimeException('Application metadata could not be restored.');
                } else {
                    $relative = substr($path, strlen('storage/public/'));
                    $this->assertNoLocalSymlinks('public', $relative);
                    $fileStream = fopen($stagedPath, 'rb');
                    if (! is_resource($fileStream)) throw new RuntimeException('An uploaded application file could not be restored.');
                    try {
                        if (! Storage::disk('public')->put($relative, $fileStream)) throw new RuntimeException('An uploaded application file could not be restored.');
                    } finally {
                        fclose($fileStream);
                    }
                }
                $restored++;
            }
        } finally {
            $zip->close();
        }

        return ['files' => $restored];
    }

    private function safeArchivePath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || str_contains($path, "\\") || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path) || preg_match('/[\x00-\x1F]/', $path)) return null;
        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) return null;
        if ($path === self::MAINTENANCE_FILE) return $path;
        foreach (self::PREFIXES as $prefix) if (str_starts_with($path, $prefix)) return $path;
        return null;
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes) || $opsys !== ZipArchive::OPSYS_UNIX) return false;
        return (($attributes >> 16) & 0170000) === 0120000;
    }

    private function writeAll($stream, string $contents): void
    {
        $offset = 0;
        while ($offset < strlen($contents)) {
            $written = fwrite($stream, substr($contents, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Private backup staging could not be written.');
            $offset += $written;
        }
    }

    private function verifyEntry(ZipArchive $zip, array $file): void
    {
        $stream = $zip->getStream($file['path']);
        if (! is_resource($stream)) throw new RuntimeException('Application file backup could not be read.');
        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) throw new RuntimeException('Application file backup could not be read.');
                if ($chunk === '') continue;
                $bytes += strlen($chunk);
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }
        if ($bytes !== $file['bytes'] || ! hash_equals($file['sha256'], hash_final($hash))) {
            throw new RuntimeException('Application file backup integrity validation failed.');
        }
    }

    private function assertNoLocalSymlinks(string $diskName, string $relative): void
    {
        if (config("filesystems.disks.{$diskName}.driver") !== 'local') return;
        $root = realpath((string) config("filesystems.disks.{$diskName}.root"));
        if ($root === false) throw new RuntimeException('Managed upload storage is unavailable.');
        $current = $root;
        foreach (explode('/', $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) throw new RuntimeException('Managed uploads cannot include symbolic links.');
            if (! file_exists($current)) continue;
            $resolved = realpath($current);
            if ($resolved === false || ($resolved !== $root && ! str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Managed upload path is outside its configured storage.');
            }
            $current = $resolved;
        }
    }
}
