<?php

namespace App\Services\Backups;

use PDO;
use RuntimeException;
use Throwable;

class MysqlDatabaseBackup
{
    public function export(string $destination): array
    {
        $connection = app('db')->connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Database backups currently require MySQL or MariaDB.');
        }

        $pdo = $connection->getPdo();
        $this->assertSupportedObjects($pdo);
        $tables = $this->tables($pdo);
        $this->assertTransactionalTables($pdo);
        $gzip = gzopen($destination, 'wb9');
        if (! is_resource($gzip)) throw new RuntimeException('Database backup output could not be created.');

        $counts = [];
        try {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $ordered = $this->orderTables($tables);
            $this->writeRecord($gzip, ['kind' => 'header', 'format' => 1, 'driver' => 'mysql', 'tables' => $ordered]);

            foreach ($ordered as $table) {
                $quoted = $this->identifier($table);
                $definition = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_ASSOC);
                $create = $definition['Create Table'] ?? null;
                if (! is_string($create) || ! str_starts_with($create, 'CREATE TABLE')) {
                    throw new RuntimeException('A database table definition could not be read.');
                }

                $columns = $pdo->query("SHOW FULL COLUMNS FROM {$quoted}")->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_column($columns, 'Field');
                $binaryColumns = [];
                foreach ($columns as $column) {
                    if (preg_match('/(?:blob|binary|bit|geometry)/i', (string) $column['Type'])) {
                        $binaryColumns[] = $column['Field'];
                    }
                }

                $this->writeRecord($gzip, [
                    'kind' => 'table', 'name' => $table, 'create' => $create,
                    'columns' => $columnNames, 'binary' => $binaryColumns,
                ]);
                $statement = $pdo->query("SELECT * FROM {$quoted}");
                $count = 0;
                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $values = [];
                    foreach ($columnNames as $column) {
                        $value = $row[$column];
                        $values[$column] = in_array($column, $binaryColumns, true) && $value !== null
                            ? ['__planora_binary' => base64_encode($value)]
                            : $value;
                    }
                    $this->writeRecord($gzip, ['kind' => 'row', 'table' => $table, 'values' => $values]);
                    $count++;
                }
                $counts[$table] = $count;
            }
            $this->writeRecord($gzip, ['kind' => 'complete', 'counts' => $counts]);
            if (! gzclose($gzip)) throw new RuntimeException('Database backup output could not be finalized.');
            if (! $pdo->commit()) throw new RuntimeException('Database backup snapshot could not be completed.');
        } catch (Throwable $exception) {
            if (is_resource($gzip)) gzclose($gzip);
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink($destination);
            throw $exception;
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            @unlink($destination);
            throw new RuntimeException('Database backup did not complete.');
        }

        return ['tables' => count($tables), 'rows' => array_sum($counts), 'counts' => $counts];
    }

    public function inspectCompressedDump(string $source): array
    {
        $stream = gzopen($source, 'rb');
        if (! is_resource($stream)) throw new RuntimeException('Database backup could not be opened.');
        $header = json_decode((string) gzgets($stream), true);
        if (($header['kind'] ?? null) !== 'header' || ($header['format'] ?? null) !== 1
            || ($header['driver'] ?? null) !== 'mysql' || ! is_array($header['tables'] ?? null)) {
            gzclose($stream);
            throw new RuntimeException('Database backup format is invalid.');
        }

        $tableNames = [];
        $complete = false;
        while (! gzeof($stream)) {
            $line = gzgets($stream);
            if ($line === false) break;
            $record = json_decode($line, true);
            if (! is_array($record) || ! is_string($record['kind'] ?? null)) {
                gzclose($stream);
                throw new RuntimeException('Database backup integrity validation failed.');
            }
            if ($record['kind'] === 'table') {
                if (! $this->validName($record['name'] ?? null) || ! is_string($record['create'] ?? null)
                    || ! str_starts_with($record['create'], 'CREATE TABLE') || ! is_array($record['columns'] ?? null)) {
                    gzclose($stream);
                    throw new RuntimeException('Database backup contains an invalid table definition.');
                }
                $tableNames[] = $record['name'];
            } elseif ($record['kind'] === 'row') {
                if (! $this->validName($record['table'] ?? null) || ! is_array($record['values'] ?? null)) {
                    gzclose($stream);
                    throw new RuntimeException('Database backup contains invalid row data.');
                }
            } elseif ($record['kind'] === 'complete') {
                $complete = true;
                break;
            } else {
                gzclose($stream);
                throw new RuntimeException('Database backup contains an unsupported record.');
            }
        }
        $valid = $complete && $tableNames === $header['tables'];
        gzclose($stream);
        if (! $valid) throw new RuntimeException('Database backup is incomplete.');

        return ['tables' => count($tableNames), 'names' => $tableNames];
    }

    public function restoreTo(PDO $pdo, string $source): array
    {
        $stream = gzopen($source, 'rb');
        if (! is_resource($stream)) throw new RuntimeException('Database backup could not be opened.');
        $header = json_decode((string) gzgets($stream), true);
        if (($header['kind'] ?? null) !== 'header' || ($header['format'] ?? null) !== 1) {
            gzclose($stream);
            throw new RuntimeException('Database backup format is invalid.');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $inserts = [];
        $counts = [];
        try {
            while (! gzeof($stream)) {
                $line = gzgets($stream);
                if ($line === false) break;
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (($record['kind'] ?? null) === 'table') {
                    $name = $record['name'] ?? null;
                    if (! $this->validName($name) || ! is_string($record['create'] ?? null)) {
                        throw new RuntimeException('Database backup contains an invalid table definition.');
                    }
                    $pdo->exec($record['create']);
                    $columns = $record['columns'] ?? [];
                    if (! is_array($columns) || ! $columns) throw new RuntimeException('Database backup table columns are invalid.');
                    foreach ($columns as $column) if (! $this->validName($column)) throw new RuntimeException('Database backup contains an invalid column name.');
                    $quoted = implode(', ', array_map(fn ($column) => $this->identifier($column), $columns));
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $inserts[$name] = $pdo->prepare('INSERT INTO '.$this->identifier($name)." ({$quoted}) VALUES ({$placeholders})");
                    $counts[$name] = 0;
                } elseif (($record['kind'] ?? null) === 'row') {
                    $table = $record['table'] ?? null;
                    if (! isset($inserts[$table]) || ! is_array($record['values'] ?? null)) throw new RuntimeException('Database backup row does not match its table definition.');
                    $values = $record['values'];
                    $index = 1;
                    foreach ($values as $value) {
                        if (is_array($value) && array_key_exists('__planora_binary', $value)) {
                            $decoded = base64_decode((string) $value['__planora_binary'], true);
                            if ($decoded === false) throw new RuntimeException('Database backup binary data is invalid.');
                            $inserts[$table]->bindValue($index++, $decoded, PDO::PARAM_LOB);
                        } else {
                            $inserts[$table]->bindValue($index++, $value);
                        }
                    }
                    $inserts[$table]->execute();
                    $counts[$table]++;
                } elseif (($record['kind'] ?? null) === 'complete') {
                    gzclose($stream);
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                    return ['tables' => count($counts), 'rows' => array_sum($counts), 'counts' => $counts];
                } else {
                    throw new RuntimeException('Database backup contains an unsupported record.');
                }
            }
            throw new RuntimeException('Database backup is incomplete.');
        } finally {
            if (is_resource($stream)) gzclose($stream);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function assertSupportedObjects(PDO $pdo): void
    {
        $nonTables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($nonTables as $row) {
            if (($row[1] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('The database contains a view or unsupported table object; no partial backup was created.');
            }
        }
        if ($pdo->query('SHOW TRIGGERS')->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('The database contains triggers not supported by this backup format.');
        }
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?');
        $statement->execute([$database]);
        if ((int) $statement->fetchColumn() > 0) throw new RuntimeException('The database contains routines not supported by this backup format.');
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?');
        $statement->execute([$database]);
        if ((int) $statement->fetchColumn() > 0) throw new RuntimeException('The database contains scheduled events not supported by this backup format.');
    }

    private function tables(PDO $pdo): array
    {
        $rows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        return array_map(fn ($row) => (string) $row[0], $rows);
    }

    private function assertTransactionalTables(PDO $pdo): void
    {
        $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tables as $table) {
            if (strtolower((string) ($table['ENGINE'] ?? '')) !== 'innodb') {
                throw new RuntimeException('Database backups require InnoDB tables to guarantee a consistent snapshot.');
            }
        }
    }

    private function orderTables(array $tables): array
    {
        $priority = ['users' => 0, 'categories' => 1, 'projects' => 2, 'tasks' => 3];
        usort($tables, fn ($left, $right) => ($priority[$left] ?? 10) <=> ($priority[$right] ?? 10) ?: strcmp($left, $right));
        return $tables;
    }

    private function writeRecord($stream, array $record): void
    {
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n";
        if (gzwrite($stream, $line) !== strlen($line)) throw new RuntimeException('Database backup could not be written.');
    }

    private function identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function validName(mixed $name): bool
    {
        return is_string($name) && preg_match('/^[A-Za-z0-9_$]+$/D', $name) === 1;
    }
}
