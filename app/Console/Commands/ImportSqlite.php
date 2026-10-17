<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportSqlite extends Command
{
    protected $signature = 'wealthdesk:import-sqlite {source : Absolute path to the existing SQLite file} {--database=mariadb : Destination connection}';

    protected $description = 'Copy and verify existing SQLite business records into an empty, migrated MariaDB database';

    public function handle(): int
    {
        $tables = ['users', 'clients', 'positions', 'deposits', 'withdrawals', 'documents', 'workspace_settings', 'audit_events'];
        $sourcePath = realpath($this->argument('source'));
        if (! $sourcePath || ! is_file($sourcePath)) {
            $this->error('The SQLite source file does not exist.');

            return self::FAILURE;
        }
        if (! app()->isDownForMaintenance()) {
            $this->error('Run php artisan down before importing, to prevent writes during the database switch.');

            return self::FAILURE;
        }
        config(['database.connections.wealthdesk_import' => ['driver' => 'sqlite', 'database' => $sourcePath, 'prefix' => '', 'foreign_key_constraints' => true]]);
        $source = DB::connection('wealthdesk_import');
        $target = DB::connection($this->option('database'));
        try {
            if (! in_array($target->getDriverName(), ['mysql', 'mariadb'], true) || ! str_contains(strtolower($target->selectOne('select version() as version')->version), 'mariadb')) {
                throw new RuntimeException('The destination must be MariaDB.');
            }
            $source->statement('PRAGMA query_only = ON');
            $source->beginTransaction();
            $sourceMigrations = $source->table('migrations')->orderBy('migration')->pluck('migration')->all();
            $targetMigrations = $target->table('migrations')->orderBy('migration')->pluck('migration')->all();
            if ($sourceMigrations !== $targetMigrations) {
                throw new RuntimeException('Source and destination migrations differ. Apply all migrations to both databases first.');
            }
            $counts = [];
            $target->transaction(function () use ($source, $target, $tables, &$counts) {
                foreach ($tables as $table) {
                    if ($target->table($table)->exists()) {
                        throw new RuntimeException("Destination {$table} is not empty. No records were imported.");
                    }
                }
                foreach ($tables as $table) {
                    $counts[$table] = 0;
                    $source->table($table)->orderBy('id')->chunk(200, function ($rows) use ($table, $target, &$counts) {
                        foreach ($rows as $row) {
                            $original = (array) $row;
                            // Query builder preserves password hashes, ciphertext, IDs and JSON without model mutations.
                            $target->table($table)->insert($original);
                            $stored = (array) $target->table($table)->where('id', $original['id'])->first();
                            foreach ($original as $column => $value) {
                                $actual = $stored[$column] ?? null;
                                if (($value === null) !== ($actual === null) || (string) $value !== (string) $actual) {
                                    throw new RuntimeException("Verification failed for {$table}.{$column}. Import rolled back.");
                                }
                            }
                            $counts[$table]++;
                        }
                    });
                    if ($target->table($table)->count() !== $counts[$table]) {
                        throw new RuntimeException("Row count mismatch for {$table}. Import rolled back.");
                    }
                }
            });
            $this->table(['Table', 'Verified rows'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all());
            $this->info('Import verified. Source SQLite was not changed. Keep the same APP_KEY and private uploads. Sessions, caches, jobs and reset tokens were not imported.');
            $this->info('Set DB_CONNECTION=mariadb and its credentials, clear config, then run php artisan up.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Do not print SQL, bindings, client data or credentials from database exceptions.
            $this->error($exception instanceof RuntimeException && ! $exception instanceof QueryException ? $exception->getMessage() : 'Database import failed. Check connectivity, schema and data compatibility. Business data writes were rolled back.');

            return self::FAILURE;
        } finally {
            if ($source->transactionLevel() > 0) {
                $source->rollBack();
            }
            DB::disconnect('wealthdesk_import');
        }
    }
}
