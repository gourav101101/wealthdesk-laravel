<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Deposit;
use App\Models\Document;
use App\Models\Position;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Ledger;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(User $user): static
    {
        $this->flushSession();
        $this->actingAs($user);

        return $this;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'password' => 'AdminTest123!']);
    }

    private function client(string $name = 'Aarav Sharma', int $investment = 1000000): Client
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'client', 'password' => 'ClientTest123!']);

        return Client::create(['user_id' => $user->id, 'code' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)), 'phone' => '9000000000', 'risk' => 'Medium', 'investment' => $investment, 'details' => ['first_name' => explode(' ', $name)[0], 'bank_name' => 'Test Bank']]);
    }

    private function positionData(Client $client): array
    {
        return ['client_id' => $client->id, 'category' => 'Equity', 'section' => 'Last trades', 'stock' => 'RELIANCE', 'date' => '2026-10-17T10:30', 'side' => 'Buy', 'quantity' => 10, 'entry_price' => '100', 'ltp' => '150', 'exit_price' => '150', 'pnl' => '500', 'state' => 'Closed'];
    }

    public function test_login_supports_email_and_client_id_without_external_auth(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Email address or Client ID')->assertDontSee('Continue with ChatGPT');
        $this->post('/login', ['login' => $admin->email, 'password' => 'AdminTest123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['login' => strtolower($client->code), 'password' => 'ClientTest123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($client->user);
    }

    public function test_client_cannot_access_administrator_actions_or_other_client_records(): void
    {
        $one = $this->client('Visible Client');
        $two = $this->client('Hidden Client');
        Position::create(collect($this->positionData($two))->except(['entry_price', 'ltp', 'exit_price', 'pnl'])->all() + ['entry_price' => 10000, 'ltp' => 15000, 'exit_price' => 15000, 'pnl' => 50000]);
        $this->signIn($one->user);
        $this->get('/')->assertOk()->assertSee('Visible')->assertDontSee('Hidden Client');
        $this->get('/admin/clients')->assertForbidden();
        $this->post('/admin/deposits', [])->assertForbidden();
        $this->get('/admin/clients/'.$two->id)->assertForbidden();
        $this->get('/positions?section=Last+trades')->assertOk()->assertDontSee('RELIANCE');
        $this->get('/export/clients')->assertOk()->assertDownload('wealthdesk-clients.csv');
    }

    public function test_administrator_screens_render_and_client_can_be_created_and_edited(): void
    {
        $this->signIn($this->admin());
        foreach (['/', '/admin/clients', '/admin/clients/create', '/positions', '/admin/positions/create', '/transactions', '/admin/deposits/create', '/withdrawals/request', '/admin/settings', '/activity', '/funds', '/coming-soon/reports'] as $url) {
            $this->get($url)->assertOk()->assertSee('<main class="content">', false)->assertDontSee('@endsection');
        }
        $input = ['first_name' => 'Mira', 'surname' => 'Shah', 'email' => 'mira@example.com', 'phone' => '9000000001', 'risk' => 'Low', 'investment' => '10000.01', 'password' => 'ClientTest123!', 'pan' => 'ABCDE1234F', 'ifsc' => 'ABCD0123456', 'aadhaar' => '123456789012', 'bank_name' => 'Sample Bank'];
        $this->post('/admin/clients', $input)->assertSessionHasNoErrors();
        $client = Client::with('user')->firstOrFail();
        $this->assertMatchesRegularExpression('/^(?=.*[A-F])(?=.*\d)[A-F0-9]{6}$/', $client->code);
        $this->assertSame(1000001, $client->investment);
        $this->assertTrue(Hash::check('ClientTest123!', $client->user->password));
        $this->assertSame('Sample Bank', $client->details['bank_name']);
        $this->get('/admin/clients/'.$client->id)->assertOk()->assertSee('Mira Shah');
        $this->get('/admin/clients/'.$client->id.'/edit')->assertOk();
        $this->put('/admin/clients/'.$client->id, array_replace($input, ['first_name' => 'Mira Updated', 'password' => '']))->assertSessionHasNoErrors();
        $this->assertSame('Mira Updated Shah', $client->user->fresh()->name);
    }

    public function test_completed_deposits_and_closed_trades_affect_balance_but_pending_deposits_do_not(): void
    {
        $client = $this->client();
        $this->signIn($this->admin());
        foreach (['Completed' => '5000', 'Pending' => '2000'] as $status => $amount) {
            $this->post('/admin/deposits', ['client_id' => $client->id, 'date' => '2026-10-17T10:30', 'amount' => $amount, 'processed_by' => 'QA', 'reference' => 'REF-'.$status, 'status' => $status])->assertSessionHasNoErrors();
        }
        $this->post('/admin/positions', $this->positionData($client))->assertSessionHasNoErrors();
        $balance = app(Ledger::class)->balance($client);
        $this->assertSame(1550000, $balance['available']);
        $this->assertSame(500000, $balance['deposits']);
        $this->assertSame(50000, $balance['realized']);
        $this->post('/admin/positions', array_replace($this->positionData($client), ['category' => 'Forex & Currency', 'section' => 'Orders']))->assertSessionHasErrors('section');
    }

    public function test_withdrawals_reserve_funds_ignore_forged_client_and_reject_overdrafts(): void
    {
        $one = $this->client();
        $two = $this->client('Other Client');
        $this->signIn($one->user);
        $this->post('/withdrawals', ['client_id' => $two->id, 'amount' => '9000', 'note' => 'Test'])->assertSessionHasNoErrors();
        $withdrawal = Withdrawal::firstOrFail();
        $this->assertSame($one->id, $withdrawal->client_id);
        $this->assertSame('Pending', $withdrawal->status);
        $this->assertSame(100000, app(Ledger::class)->balance($one)['available']);
        $this->post('/withdrawals', ['amount' => '1000.01'])->assertSessionHasErrors('amount');
        $this->assertSame(1, Withdrawal::count());
        $this->post('/withdrawals', ['amount' => '1000'])->assertSessionHasNoErrors();
        $this->post('/withdrawals', ['amount' => '0.01'])->assertSessionHasErrors('amount');
        $this->assertSame(0, app(Ledger::class)->balance($one)['available']);
    }

    public function test_withdrawal_decisions_are_admin_only_and_cannot_be_reversed(): void
    {
        $client = $this->client();
        $withdrawal = Withdrawal::create(['client_id' => $client->id, 'amount' => 400000, 'status' => 'Pending']);
        $this->signIn($client->user)->post('/admin/withdrawals/'.$withdrawal->id.'/decision', ['status' => 'Approved'])->assertForbidden();
        $admin = $this->admin();
        $this->signIn($admin)->post('/admin/withdrawals/'.$withdrawal->id.'/decision', ['status' => 'Approved'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $withdrawal->fresh()->status);
        $this->assertSame($admin->id, $withdrawal->fresh()->decided_by);
        $this->post('/admin/withdrawals/'.$withdrawal->id.'/decision', ['status' => 'Declined'])->assertSessionHasErrors('withdrawal');
        $this->assertSame(600000, app(Ledger::class)->balance($client)['available']);
        $second = Withdrawal::create(['client_id' => $client->id, 'amount' => 100000, 'status' => 'Pending']);
        $this->post('/admin/withdrawals/'.$second->id.'/decision', ['status' => 'Declined'])->assertSessionHasNoErrors();
        $this->assertSame(600000, app(Ledger::class)->balance($client)['available']);
    }

    public function test_deposit_removal_and_negative_trade_edit_cannot_break_reserved_funds(): void
    {
        $client = $this->client();
        $deposit = Deposit::create(['client_id' => $client->id, 'date' => now(), 'amount' => 500000, 'processed_by' => 'QA', 'reference' => 'RESERVE-1', 'status' => 'Completed']);
        Withdrawal::create(['client_id' => $client->id, 'amount' => 1400000, 'status' => 'Pending']);
        $this->signIn($this->admin())->delete('/admin/deposits/'.$deposit->id)->assertSessionHasErrors('amount');
        $this->assertDatabaseHas('deposits', ['id' => $deposit->id, 'deleted_at' => null]);
        $this->post('/admin/positions', array_replace($this->positionData($client), ['pnl' => '-2000']))->assertSessionHasErrors('amount');
        $this->assertSame(0, Position::count());
        $this->assertSame(100000, app(Ledger::class)->balance($client)['available']);
    }

    public function test_hold_resume_remove_and_duplicate_transaction_reference(): void
    {
        $client = $this->client();
        $this->signIn($this->admin());
        $this->post('/admin/positions', array_replace($this->positionData($client), ['section' => 'Current positions', 'state' => 'Active']))->assertSessionHasNoErrors();
        $position = Position::firstOrFail();
        $this->post('/admin/positions/'.$position->id.'/action', ['action' => 'hold'])->assertSessionHasNoErrors();
        $this->assertSame('Hold', $position->fresh()->state);
        $this->post('/admin/positions/'.$position->id.'/action', ['action' => 'hold'])->assertSessionHasNoErrors();
        $this->assertSame('Active', $position->fresh()->state);
        $this->post('/admin/positions/'.$position->id.'/action', ['action' => 'remove'])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($position);
        $deposit = ['client_id' => $client->id, 'date' => '2026-10-17T10:30', 'amount' => '100', 'processed_by' => 'QA', 'reference' => 'UNIQUE-REF', 'status' => 'Completed'];
        $this->post('/admin/deposits', $deposit)->assertSessionHasNoErrors();
        $this->post('/admin/deposits', $deposit)->assertSessionHasErrors('reference');
    }

    public function test_password_change_is_hashed_audited_and_visible_to_admin(): void
    {
        $client = $this->client();
        $this->signIn($client->user);
        $this->get('/profile')->assertOk()->assertSee('Change password');
        $this->post('/profile/password', ['current_password' => 'wrong', 'password' => 'NewClient123!', 'password_confirmation' => 'NewClient123!'])->assertSessionHasErrors('current_password');
        $this->post('/profile/password', ['current_password' => 'ClientTest123!', 'password' => 'NewClient123!', 'password_confirmation' => 'NewClient123!'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewClient123!', $client->user->fresh()->password));
        $this->assertNotNull($client->user->fresh()->password_changed_at);
        $this->assertDatabaseHas('audit_events', ['client_id' => $client->id, 'action' => 'password']);
        $this->signIn($this->admin())->get('/admin/clients')->assertOk()->assertSee('Password changed')->assertDontSee('NewClient123!');
    }

    public function test_documents_are_private_and_clients_can_only_change_their_own_photo(): void
    {
        Storage::fake('local');
        $one = $this->client();
        $two = $this->client();
        $this->signIn($one->user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('photo.png')])->assertSessionHasNoErrors();
        $doc = Document::firstOrFail();
        Storage::disk('local')->assertExists($doc->path);
        $this->get('/documents/'.$doc->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->signIn($two->user)->get('/documents/'.$doc->id)->assertForbidden();
        $this->signIn($one->user)->post('/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')])->assertSessionHasErrors('photo');
        $this->signIn($this->admin())->get('/documents/'.$doc->id)->assertOk();
    }

    public function test_archival_preserves_history_disables_access_and_pending_requests_block_archival(): void
    {
        $client = $this->client();
        $withdrawal = Withdrawal::create(['client_id' => $client->id, 'amount' => 100, 'status' => 'Pending']);
        $admin = $this->admin();
        $this->signIn($admin)->post('/admin/clients/'.$client->id.'/archive')->assertSessionHasErrors('client');
        $withdrawal->update(['status' => 'Declined']);
        $this->post('/admin/clients/'.$client->id.'/archive')->assertSessionHasNoErrors();
        $this->assertTrue($client->fresh()->archived);
        $this->assertDatabaseHas('withdrawals', ['id' => $withdrawal->id]);
        $this->signIn($client->user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
        $this->signIn($admin)->post('/admin/clients/'.$client->id.'/archive')->assertSessionHasNoErrors();
        $this->assertFalse($client->fresh()->archived);
    }

    public function test_qr_settings_are_available_to_clients_and_csv_injection_is_escaped(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $client = $this->client('=SUM(A1)');
        $this->signIn($admin)->post('/admin/settings', ['name' => 'Example Capital', 'payment_name' => 'Example Payee', 'upi_id' => 'example@bank', 'payment_note' => 'Include Client ID', 'qr' => UploadedFile::fake()->image('qr.png')])->assertSessionHasNoErrors();
        $qr = Document::where('kind', 'qr')->firstOrFail();
        $this->signIn($client->user)->get('/funds')->assertOk()->assertSee('Example Payee')->assertSee('example@bank');
        $this->get('/documents/'.$qr->id)->assertOk();
        $export = $this->signIn($admin)->get('/export/clients')->assertOk();
        $this->assertStringContainsString("'=SUM(A1)", $export->streamedContent());
    }

    public function test_money_conversion_uses_exact_paise(): void
    {
        $this->assertSame(100001,Ledger::paise('1000.01'));
        $this->assertSame(-10,Ledger::paise('-0.10'));
        $this->assertSame('₹12,34,567.89',Format::money(123456789));
    }
}
