<?php

namespace Tests\Feature;

use App\Models\WorkspaceSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_keep_their_singleton_id_after_the_sequence_advances(): void
    {
        WorkspaceSetting::forceCreate(['id' => 50, 'name' => 'Temporary'])->delete();
        $settings = WorkspaceSetting::current();
        $this->assertSame(1, $settings->id);
        $settings->update(['payment_name' => 'Saved payee']);
        $this->assertSame('Saved payee', WorkspaceSetting::current()->payment_name);
        $this->assertSame(1, WorkspaceSetting::count());
    }
}
