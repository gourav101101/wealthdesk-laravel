<?php

namespace Tests\Feature;

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ImportSqliteTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sourcePath = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mariadb') {
            $this->markTestSkipped('Import integration tests require the separate MariaDB testing database.');
        }
        $this->sourcePath = tempnam(storage_path('framework/testing'), 'import-');
        config(['database.connections.import_fixture' => ['driver' => 'sqlite', 'database' => $this->sourcePath, 'prefix' => '', 'foreign_key_constraints' => true]]);
        $this->artisan('migrate', ['--database' => 'import_fixture', '--force' => true])->assertExitCode(0);
        $this->app->instance(MaintenanceMode::class, Mockery::mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true)->getMock());
    }

    protected function tearDown(): void
    {
        DB::purge('wealthdesk_import');
        DB::purge('import_fixture');
        if ($this->sourcePath && is_file($this->sourcePath)) {
            unlink($this->sourcePath);
        }
        parent::tearDown();
    }

    public function test_import_preserves_hashes_ciphertext_and_ids_and_refuses_existing_data(): void
    {
        $hash = Hash::make('ImportTest123!');
        $cipher = Crypt::encryptString('ImportTest123!');
        DB::connection('import_fixture')->table('users')->insert(['id' => 42, 'name' => 'Import fixture', 'email' => 'fixture@example.test', 'password' => $hash, 'client_password' => $cipher]);
        $this->artisan('wealthdesk:import-sqlite', ['source' => $this->sourcePath, '--database' => config('database.default')])->assertExitCode(0);
        $user = DB::table('users')->where('id', 42)->first();
        $this->assertSame($hash, $user->password);
        $this->assertSame($cipher, $user->client_password);
        $this->assertSame('ImportTest123!', Crypt::decryptString($user->client_password));
        $this->artisan('wealthdesk:import-sqlite', ['source' => $this->sourcePath, '--database' => config('database.default')])->assertExitCode(1);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::connection('import_fixture')->table('users')->count());
    }

    public function test_late_import_failure_rolls_back_earlier_records(): void
    {
        $source = DB::connection('import_fixture');
        $source->table('users')->insert(['id' => 42, 'name' => 'Import fixture', 'email' => 'fixture@example.test', 'password' => Hash::make('ImportTest123!')]);
        // SQLite permits this text; strict MariaDB rejects it after users have been inserted.
        $source->table('workspace_settings')->insert(['id' => 1, 'name' => str_repeat('x', 300)]);
        $this->artisan('wealthdesk:import-sqlite', ['source' => $this->sourcePath, '--database' => config('database.default')])->assertExitCode(1);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('workspace_settings')->count());
        $this->assertSame(1, $source->table('users')->count());
    }
}
