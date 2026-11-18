<?php

namespace App\Services\Backups;

use RuntimeException;

class BackupCipher
{
    private const MAGIC = "PLANORA-BACKUP\x01";
    private const CHUNK_BYTES = 1048576;

    public function encrypt(string $source, string $destination, string $backupId): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if (! is_resource($input) || ! is_resource($output)) {
            throw new RuntimeException('Backup encryption could not be started.');
        }

        $key = $this->key();
        $prefix = random_bytes(8);
        try {
            $this->write($output, self::MAGIC.$prefix);
            $counter = 0;
            while (! feof($input)) {
                $plain = fread($input, self::CHUNK_BYTES);
                if ($plain === false) throw new RuntimeException('Backup encryption could not read its input.');
                if ($plain === '') continue;

                $tag = '';
                $nonce = $prefix.pack('N', $counter++);
                $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $backupId, 16);
                if ($cipher === false || strlen($tag) !== 16) throw new RuntimeException('Backup encryption failed.');
                $this->write($output, pack('N', strlen($cipher)).$tag.$cipher);
            }
            $this->write($output, pack('N', 0));
        } finally {
            fclose($input);
            fclose($output);
            unset($key);
        }
    }

    public function decrypt(string $source, string $destination, string $backupId): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if (! is_resource($input) || ! is_resource($output)) {
            throw new RuntimeException('Backup could not be opened.');
        }

        $key = $this->key();
        try {
            $header = $this->read($input, strlen(self::MAGIC) + 8);
            if (! str_starts_with($header, self::MAGIC)) throw new RuntimeException('Backup format is not supported.');
            $prefix = substr($header, strlen(self::MAGIC));
            $counter = 0;
            $complete = false;

            while (! feof($input)) {
                $sizeBytes = fread($input, 4);
                if ($sizeBytes === false || strlen($sizeBytes) !== 4) break;
                $size = unpack('Nsize', $sizeBytes)['size'];
                if ($size === 0) {
                    $complete = fread($input, 1) === '' && feof($input);
                    break;
                }
                if ($size > self::CHUNK_BYTES || $size < 1) throw new RuntimeException('Backup integrity validation failed.');

                $tag = $this->read($input, 16);
                $cipher = $this->read($input, $size);
                $nonce = $prefix.pack('N', $counter++);
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $backupId);
                if ($plain === false) throw new RuntimeException('Backup integrity validation failed.');
                $this->write($output, $plain);
            }

            if (! $complete || fread($input, 1) !== '') throw new RuntimeException('Backup is incomplete or has trailing data.');
        } finally {
            fclose($input);
            fclose($output);
            unset($key);
        }
    }

    private function key(): string
    {
        $configured = config('backups.encryption_key');
        if (is_string($configured) && $configured !== '') {
            $decoded = base64_decode($configured, true);
            if ($decoded === false || strlen($decoded) !== 32) {
                throw new RuntimeException('Backup encryption is not configured correctly.');
            }

            return $decoded;
        }

        $appKey = (string) config('app.key');
        if (str_starts_with($appKey, 'base64:')) $appKey = base64_decode(substr($appKey, 7), true) ?: '';
        if (strlen($appKey) < 32) throw new RuntimeException('A stable application encryption key is required for backups.');

        return hash_hkdf('sha256', $appKey, 32, 'planora-backup-encryption-v1', 'planora-backups');
    }

    private function read($stream, int $length): string
    {
        $value = '';
        while (strlen($value) < $length && ! feof($stream)) {
            $part = fread($stream, $length - strlen($value));
            if ($part === false || $part === '') break;
            $value .= $part;
        }
        if (strlen($value) !== $length) throw new RuntimeException('Backup is incomplete.');

        return $value;
    }

    private function write($stream, string $value): void
    {
        $written = 0;
        while ($written < strlen($value)) {
            $size = fwrite($stream, substr($value, $written));
            if ($size === false || $size === 0) throw new RuntimeException('Backup could not be written.');
            $written += $size;
        }
    }
}
