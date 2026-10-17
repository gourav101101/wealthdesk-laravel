<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientPasswordVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'password' => 'AdminTest123!']);
    }

    private function client(): Client
    {
        $user = User::factory()->create(['role' => 'client', 'password' => 'ClientTest123!']);

        return Client::create(['user_id' => $user->id, 'code' => 'TEST1A', 'phone' => '9000000000', 'risk' => 'Medium', 'investment' => 100000, 'details' => ['first_name' => 'Test']]);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        $this->actingAs($user);

        return $this;
    }

    private function endpoint(Client $client): string
    {
        return '/admin/clients/'.$client->id.'/password/reveal';
    }

    public function test_password_copy_is_encrypted_and_excluded_from_serialized_users(): void
    {
        $client = $this->client();
        $user = $client->user;
        $stored = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame('ClientTest123!', $stored->client_password);
        $this->assertStringNotContainsString('ClientTest123!', $stored->client_password);
        $this->assertTrue(Hash::check('ClientTest123!', $stored->password));
        $this->assertSame('ClientTest123!', $user->client_password);
        $this->assertArrayNotHasKey('client_password', $user->toArray());
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertNull($this->admin()->fresh()->getRawOriginal('client_password'));
    }

    public function test_only_admin_with_their_password_can_reveal_and_each_view_is_audited(): void
    {
        $client = $this->client();
        $this->postJson($this->endpoint($client), ['current_password' => 'AdminTest123!'])->assertUnauthorized();
        $this->signIn($client->user)->postJson($this->endpoint($client), ['current_password' => 'ClientTest123!'])->assertForbidden();
        $admin = $this->admin();
        $this->signIn($admin)->get($this->endpoint($client))->assertStatus(405);
        $this->postJson($this->endpoint($client), ['current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame(0, AuditEvent::where('action', 'password_view')->count());
        $response = $this->postJson($this->endpoint($client), ['current_password' => 'AdminTest123!']);
        $response->assertOk()->assertJsonPath('password', 'ClientTest123!')->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'client_id' => $client->id, 'action' => 'password_view', 'description' => 'Viewed client password']);
        $this->assertStringNotContainsString('ClientTest123!', AuditEvent::all()->toJson());
        $this->get('/admin/clients')->assertOk()->assertSee('View current password')->assertDontSee('ClientTest123!');
        $this->get('/admin/clients/'.$client->id)->assertOk()->assertSee('View password')->assertDontSee('ClientTest123!');
        $this->get('/export/clients')->assertOk()->assertDontSee('ClientTest123!');
    }

    public function test_client_password_changes_and_admin_resets_update_the_viewable_password(): void
    {
        $client = $this->client();
        $this->signIn($client->user)->post('/profile/password', ['current_password' => 'ClientTest123!', 'password' => 'UpdatedClient123!', 'password_confirmation' => 'UpdatedClient123!'])->assertSessionHasNoErrors();
        $this->assertSame('UpdatedClient123!', $client->user->fresh()->client_password);
        $this->assertNotNull($client->user->fresh()->password_changed_at);
        $this->get('/profile')->assertDontSee('data-reveal-client-password')->assertDontSee('UpdatedClient123!');
        $this->signIn($this->admin())->postJson($this->endpoint($client), ['current_password' => 'AdminTest123!'])->assertOk()->assertJsonPath('password', 'UpdatedClient123!');
        $this->put('/admin/clients/'.$client->id, ['first_name' => 'Test', 'email' => $client->user->email, 'phone' => $client->phone, 'risk' => $client->risk, 'investment' => '1000', 'password' => 'ResetClient123!'])->assertSessionHasNoErrors();
        $this->postJson($this->endpoint($client), ['current_password' => 'AdminTest123!'])->assertOk()->assertJsonPath('password', 'ResetClient123!');
        $this->assertTrue(Hash::check('ResetClient123!', $client->user->fresh()->password));
        $this->assertNull($client->user->fresh()->password_changed_at);
    }

    public function test_older_password_is_only_captured_after_successful_client_login(): void
    {
        $client = $this->client();
        DB::table('users')->where('id', $client->user_id)->update(['client_password' => null]);
        $hash = $client->user->fresh()->password;
        $this->signIn($this->admin())->postJson($this->endpoint($client), ['current_password' => 'AdminTest123!'])->assertStatus(409);
        $this->post('/logout');
        $this->post('/login', ['login' => $client->code, 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertNull($client->user->fresh()->getRawOriginal('client_password'));
        $this->post('/login', ['login' => $client->code, 'password' => 'ClientTest123!'])->assertSessionHasNoErrors();
        $this->assertSame('ClientTest123!', $client->user->fresh()->client_password);
        $this->assertSame($hash, $client->user->fresh()->password);
        $this->assertNull($client->user->fresh()->password_changed_at);
        $this->signIn($this->admin())->postJson($this->endpoint($client), ['current_password' => 'AdminTest123!'])->assertOk()->assertJsonPath('password', 'ClientTest123!');
    }

    public function test_failed_admin_confirmations_are_rate_limited(): void
    {
        $client = $this->client();
        $this->signIn($this->admin());
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->endpoint($client), ['current_password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson($this->endpoint($client), ['current_password' => 'AdminTest123!'])->assertTooManyRequests();
        $this->assertSame(0, AuditEvent::where('action', 'password_view')->count());
    }

    public function test_importing_a_new_hash_clears_the_old_viewable_copy(): void
    {
        $client = $this->client();
        $client->user->update(['password' => Hash::make('ImportedClient123!')]);
        $this->assertNull($client->user->fresh()->getRawOriginal('client_password'));
        $this->assertTrue(Hash::check('ImportedClient123!', $client->user->fresh()->password));
    }
}
