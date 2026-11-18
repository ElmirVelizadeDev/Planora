<?php

namespace App\Services\Backups;

use PDO;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\Artisan;

class MysqlDatabaseRestore
{
    public function __construct(private readonly MysqlDatabaseBackup $backup)
    {
    }

    public function restore(string $dumpPath): array
    {
        $connection = app('db')->connection();
        if ($connection->getDriverName() !== 'mysql') throw new RuntimeException('Database recovery currently requires MySQL or MariaDB.');

        $target = (string) $connection->getDatabaseName();
        if (! $this->validDatabaseName($target)) throw new RuntimeException('The configured database cannot be safely staged for recovery.');
        $expected = $this->backup->inspectCompressedDump($dumpPath);
        $suffix = bin2hex(random_bytes(8));
        $stage = 'planora_stage_'.$suffix;
        $recovery = 'planora_previous_'.$suffix;
        $control = $this->connectWithoutDatabase();
        $schema = $this->schemaOptions($control, $target);
        $stageCreated = false;
        $recoveryCreated = false;
        $swapped = false;
        $maintenanceEnabled = false;

        try {
            if (app()->isDownForMaintenance()) throw new RuntimeException('The application is already in maintenance mode; recovery was not started.');
            $this->assertOnlyTables($control, $target);
            $this->createDatabase($control, $stage, $schema);
            $stageCreated = true;
            $stagePdo = $this->connectToDatabase($stage);
            $counts = $this->backup->restoreTo($stagePdo, $dumpPath);
            $this->assertTables($stagePdo, $expected['names']);

            $this->createDatabase($control, $recovery, $schema);
            $recoveryCreated = true;
            Artisan::call('down');
            $maintenanceEnabled = true;
            $this->swap($control, $target, $stage, $recovery, $expected['names']);
            $swapped = true;

            $targetPdo = $connection->getPdo();
            $this->assertTables($targetPdo, $expected['names']);
            $this->assertRowCounts($targetPdo, $counts['counts'] ?? []);

            $control->exec('DROP DATABASE '.$this->identifier($recovery));
            $recoveryCreated = false;
            $control->exec('DROP DATABASE '.$this->identifier($stage));
            $stageCreated = false;
            Artisan::call('up');
            $maintenanceEnabled = false;

            return ['tables' => $counts['tables'], 'rows' => $counts['rows']];
        } catch (Throwable $exception) {
            if ($swapped) {
                try {
                    $this->rollbackSwap($control, $target, $stage, $recovery, $expected['names']);
                    $swapped = false;
                } catch (Throwable $rollbackException) {
                    throw new RuntimeException('Database recovery failed and automatic rollback could not complete. Preserve the recovery database and contact the database operator.', 0, $rollbackException);
                }
            }
            throw $exception;
        } finally {
            if (! $swapped) {
                if ($stageCreated) $control->exec('DROP DATABASE IF EXISTS '.$this->identifier($stage));
                if ($recoveryCreated) $control->exec('DROP DATABASE IF EXISTS '.$this->identifier($recovery));
            }
            // A failed rollback must leave the application in maintenance mode.
            // Recovery schemas are intentionally retained when $swapped is still true.
            if ($maintenanceEnabled && ! $swapped) Artisan::call('up');
        }
    }

    private function swap(PDO $control, string $target, string $stage, string $recovery, array $expectedNames): void
    {
        $existing = $this->databaseTables($control, $target);
        $rename = [];
        foreach ($existing as $table) $rename[] = $this->qualified($target, $table).' TO '.$this->qualified($recovery, $table);
        foreach ($expectedNames as $table) $rename[] = $this->qualified($stage, $table).' TO '.$this->qualified($target, $table);
        if (! $rename) throw new RuntimeException('The recovery database contains no tables.');
        $control->exec('RENAME TABLE '.implode(', ', $rename));
    }

    private function rollbackSwap(PDO $control, string $target, string $stage, string $recovery, array $expectedNames): void
    {
        $oldNames = $this->databaseTables($control, $recovery);
        $rename = [];
        foreach ($expectedNames as $table) $rename[] = $this->qualified($target, $table).' TO '.$this->qualified($stage, $table);
        foreach ($oldNames as $table) $rename[] = $this->qualified($recovery, $table).' TO '.$this->qualified($target, $table);
        if ($rename) $control->exec('RENAME TABLE '.implode(', ', $rename));
    }

    private function assertTables(PDO $pdo, array $expected): void
    {
        $actual = array_column($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM), 0);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) throw new RuntimeException('Database recovery table verification failed.');
    }

    private function assertRowCounts(PDO $pdo, array $expected): void
    {
        foreach ($expected as $table => $count) {
            $actual = (int) $pdo->query('SELECT COUNT(*) FROM '.$this->identifier($table))->fetchColumn();
            if ($actual !== (int) $count) throw new RuntimeException('Database recovery data verification failed.');
        }
    }

    private function databaseTables(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
        $statement->execute([$database]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function assertOnlyTables(PDO $pdo, string $database): void
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE <> \'BASE TABLE\'');
        $statement->execute([$database]);
        if ((int) $statement->fetchColumn() > 0) throw new RuntimeException('The configured database contains unsupported views; recovery was not started.');
    }

    private function schemaOptions(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$database]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (! $row || ! preg_match('/^[A-Za-z0-9_]+$/D', $row['DEFAULT_CHARACTER_SET_NAME'])
            || ! preg_match('/^[A-Za-z0-9_]+$/D', $row['DEFAULT_COLLATION_NAME'])) {
            throw new RuntimeException('The configured database encoding could not be verified.');
        }

        return $row;
    }

    private function createDatabase(PDO $pdo, string $name, array $schema): void
    {
        $pdo->exec('CREATE DATABASE '.$this->identifier($name).' CHARACTER SET '.$schema['DEFAULT_CHARACTER_SET_NAME'].' COLLATE '.$schema['DEFAULT_COLLATION_NAME']);
    }

    private function connectWithoutDatabase(): PDO
    {
        return $this->connect(null);
    }

    private function connectToDatabase(string $database): PDO
    {
        return $this->connect($database);
    }

    private function connect(?string $database): PDO
    {
        $config = config('database.connections.mysql');
        $socket = $config['unix_socket'] ?? '';
        $dsn = $socket !== ''
            ? 'mysql:unix_socket='.$socket.';charset=utf8mb4'
            : 'mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4';
        if ($database !== null) $dsn .= ';dbname='.$database;
        return new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function qualified(string $database, string $table): string
    {
        return $this->identifier($database).'.'.$this->identifier($table);
    }

    private function identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function validDatabaseName(string $database): bool
    {
        return preg_match('/^[A-Za-z0-9_$]+$/D', $database) === 1;
    }
}
