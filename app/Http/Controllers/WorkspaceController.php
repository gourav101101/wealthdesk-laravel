<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\Document;
use App\Models\Position;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WorkspaceSetting;
use App\Services\Ledger;
use App\Services\PrivateDocuments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    public function __construct(private Ledger $ledger) {}

    private function selected(Request $request): ?Client
    {
        if (! $request->user()->isAdmin()) {
            return $request->user()->client;
        }

        return $request->session()->has('preview_client') ? Client::with('user')->find($request->session()->get('preview_client')) : null;
    }

    private function common(Request $request): array
    {
        $selected = $this->selected($request);
        $clientQuery = Client::with(['user', 'documents']);
        if ($selected) {
            $clientQuery->whereKey($selected->id);
        }
        $clients = $clientQuery->latest('id')->get();
        $balances = $clients->mapWithKeys(fn ($c) => [$c->id => $this->ledger->balance($c)]);
        $positionQuery = Position::with('client.user');
        $depositQuery = Deposit::with('client.user');
        $withdrawalQuery = Withdrawal::with('client.user', 'decidedBy');
        $eventQuery = AuditEvent::with('actor');
        foreach ([$positionQuery, $depositQuery, $withdrawalQuery, $eventQuery] as $query) {
            if ($selected) {
                $query->where('client_id', $selected->id);
            }
        }

        return [
            'workspace' => WorkspaceSetting::current(), 'selected' => $selected, 'clients' => $clients, 'balances' => $balances,
            'adminView' => $request->user()->isAdmin() && ! $selected,
            'allPositions' => $positionQuery->latest('date')->get(), 'allDeposits' => $depositQuery->latest('date')->get(),
            'allWithdrawals' => $withdrawalQuery->latest('id')->get(), 'events' => $eventQuery->latest('id')->limit(100)->get(),
            'qr' => Document::where('kind', 'qr')->latest()->first(),
        ];
    }

    public function dashboard(Request $request)
    {
        return view('dashboard', $this->common($request));
    }

    public function clients(Request $request)
    {
        $request->session()->forget('preview_client');
        $query = Client::with('user', 'documents')->where('archived', $request->boolean('archived'));
        if ($request->filled('q')) {
            $term = '%'.mb_substr($request->string('q')->toString(), 0, 100).'%';
            $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('pan', 'like', $term)->orWhere('phone', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
        }
        if (in_array($request->get('risk'), Ledger::RISKS)) {
            $query->where('risk', $request->get('risk'));
        }

        return view('clients.index', $this->common($request) + ['rows' => $query->latest('id')->paginate(10)->withQueryString()]);
    }

    public function clientForm(Request $request, ?Client $client = null)
    {
        abort_if($client?->archived, 409, 'Restore this client before editing.');

        return view('clients.form', $this->common($request) + ['client' => $client]);
    }

    public function saveClient(Request $request, ?Client $client = null)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $input = $request->validate([
            'first_name' => 'required|string|max:60', 'middle_name' => 'nullable|string|max:60', 'surname' => 'nullable|string|max:60',
            'email' => ['required', 'email:rfc', 'max:160', Rule::unique('users')->ignore($client?->user_id)],
            'phone' => ['required', 'string', 'max:25', 'regex:/^\+?[0-9 ()-]{7,25}$/'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Za-z]{5}\d{4}[A-Za-z]$/'],
            'investment' => 'required|numeric|min:0|max:9999999999|decimal:0,2', 'risk' => ['required', Rule::in(Ledger::RISKS)],
            'password' => [$client ? 'nullable' : 'required', 'string', Password::min(10)->letters()->numbers(), 'max:128'],
            'address' => 'nullable|string|max:500', 'dob' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'registration' => 'nullable|string|max:100', 'provide' => 'nullable|string|max:200',
            'aadhaar' => 'nullable|digits:12', 'bank_name' => 'nullable|string|max:100', 'account_number' => 'nullable|string|max:100',
            'account_holder' => 'nullable|string|max:100', 'ifsc' => ['nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'account_type' => ['nullable', Rule::in(['Savings', 'Current', 'Other'])],
            'nominee' => ['nullable', Rule::in(['Spouse', 'Parent', 'Child', 'Sibling', 'Other', 'None'])],
            'nominee_name' => 'nullable|string|max:100', 'nominee_pan' => ['nullable', 'regex:/^[A-Za-z]{5}\d{4}[A-Za-z]$/'],
            'nominee_aadhaar' => 'nullable|digits:12', 'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'signature' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120', 'aadhaar_document' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        $details = collect($input)->only(['first_name', 'middle_name', 'surname', 'address', 'dob', 'registration', 'provide', 'aadhaar', 'bank_name', 'account_number', 'account_holder', 'ifsc', 'account_type', 'nominee', 'nominee_name', 'nominee_pan', 'nominee_aadhaar'])->all();
        $userData = ['name' => trim(implode(' ', array_filter([$input['first_name'], $input['middle_name'] ?? '', $input['surname'] ?? '']))), 'email' => strtolower($input['email'])];
        if (! empty($input['password'])) {
            $userData['password'] = $input['password'];
        }
        $clientData = ['phone' => $input['phone'], 'pan' => strtoupper($input['pan'] ?? ''), 'risk' => $input['risk'], 'investment' => Ledger::paise($input['investment']), 'details' => $details];
        if ($client) {
            $client = $this->ledger->change($client->id, function ($locked) use ($userData, $clientData, $input) {
                $locked->user->update($userData + (! empty($input['password']) ? ['password_changed_at' => null, 'remember_token' => null] : []));
                if (! empty($input['password'])) {
                    DB::table('sessions')->where('user_id', $locked->user_id)->delete();
                }
                $locked->update($clientData);
                Ledger::audit('client', 'Updated '.$locked->user->name, $locked->id);

                return $locked;
            });
        } else {
            $client = DB::transaction(function () use ($userData, $clientData) {
                $user = User::create($userData + ['role' => 'client']);
                do {
                    $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 6));
                } while (! preg_match('/[A-F]/', $code) || ! preg_match('/\d/', $code) || Client::where('code', $code)->exists());
                $created = Client::create($clientData + ['user_id' => $user->id, 'code' => $code]);
                Ledger::audit('client', 'Added '.$user->name, $created->id);

                return $created;
            });
        }
        $documents = app(PrivateDocuments::class);
        foreach (['photo' => 'photo', 'signature' => 'signature', 'aadhaar_document' => 'aadhaar'] as $field => $kind) {
            if ($request->hasFile($field)) {
                $documents->replace($request->file($field), $kind, $client->id);
            }
        }

        return redirect()->route('clients.show', $client)->with('success', 'Client saved. Client ID: '.$client->code);
    }

    public function clientShow(Request $request, Client $client)
    {
        return view('profile', $this->common($request) + ['profile' => $client->load('user', 'documents'), 'profileBalance' => $this->ledger->balance($client), 'ownProfile' => false]);
    }

    public function revealClientPassword(Request $request, Client $client)
    {
        $request->validate(['current_password' => 'required|string|max:128|current_password:web']);
        $user = $client->user;
        abort_if($user->isAdmin(), 403);
        $headers = ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Expires' => '0'];
        if ($user->getRawOriginal('client_password') === null) {
            return response()->json([
                'message' => 'This account predates password visibility. Its existing password cannot be recovered. Ask the client to sign in once, then try again, or set a new password using Edit client. A reset changes the client\'s login password.',
                'reset_url' => $client->archived ? null : route('clients.edit', $client),
            ], 409, $headers);
        }
        $password = $user->client_password;
        Ledger::audit('password_view', 'Viewed client password', $client->id);

        return response()->json(['password' => $password, 'changed_at' => $user->password_changed_at?->toIso8601String()], 200, $headers);
    }

    public function archiveClient(Request $request, Client $client)
    {
        $this->ledger->change($client->id, function ($locked) {
            if (! $locked->archived && $locked->withdrawals()->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages(['client' => 'Resolve pending withdrawals before archiving this client.']);
            }
            $locked->update(['archived' => ! $locked->archived]);
            $locked->user->update(['remember_token' => null]);
            Ledger::audit('client', $locked->archived ? 'Archived client; history preserved' : 'Restored client', $locked->id);
        }, true);

        return back()->with('success', 'Client account updated.');
    }

    public function preview(Request $request, Client $client)
    {
        $request->session()->put('preview_client', $client->id);

        return redirect()->route('dashboard');
    }

    public function exitPreview(Request $request)
    {
        $request->session()->forget('preview_client');

        return redirect()->route('clients.index');
    }

    public function positions(Request $request)
    {
        $data = $this->common($request);
        $category = in_array($request->get('category'), Ledger::CATEGORIES) ? $request->get('category') : 'Equity';
        $section = in_array($request->get('section'), Ledger::SECTIONS) ? $request->get('section') : 'Current positions';
        if ($category === 'Forex & Currency' && ! in_array($section, array_slice(Ledger::SECTIONS, 0, 2))) {
            $section = 'Current positions';
        }
        $rows = $data['allPositions']->where('category', $category)->where('section', $section)->filter(fn ($p) => (! $request->filled('client') || $p->client_id == $request->get('client')) && (! $request->filled('q') || str_contains(strtolower($p->stock.' '.$p->client->name), strtolower($request->get('q')))));

        return view('positions.index', $data + compact('category', 'section', 'rows'));
    }

    public function positionForm(Request $request, ?Position $position = null)
    {
        return view('positions.form', $this->common($request) + ['position' => $position]);
    }

    public function savePosition(Request $request, ?Position $position = null)
    {
        $input = $request->validate([
            'client_id' => 'required|integer|exists:clients,id', 'category' => ['required', Rule::in(Ledger::CATEGORIES)],
            'section' => ['required', Rule::in(Ledger::SECTIONS)], 'stock' => 'required|string|max:100',
            'date' => 'required|date', 'side' => ['required', Rule::in(['Buy', 'Sell'])], 'quantity' => 'required|integer|min:1|max:100000000',
            'entry_price' => 'required|numeric|min:0|max:9999999999|decimal:0,2', 'ltp' => 'required|numeric|min:0|max:9999999999|decimal:0,2',
            'exit_price' => 'nullable|required_if:section,Last trades|numeric|min:0|max:9999999999|decimal:0,2',
            'pnl' => 'required|numeric|between:-9999999999,9999999999|decimal:0,2', 'state' => ['required', Rule::in(['Active', 'Hold', 'Closed'])],
        ]);
        if ($position && $position->client_id !== (int) $input['client_id']) {
            throw ValidationException::withMessages(['client_id' => 'Saved positions cannot be moved between clients.']);
        }
        if ($input['category'] === 'Forex & Currency' && ! in_array($input['section'], array_slice(Ledger::SECTIONS, 0, 2))) {
            throw ValidationException::withMessages(['section' => 'Forex supports current positions and last trades.']);
        }
        foreach (['entry_price', 'ltp', 'pnl'] as $key) {
            $input[$key] = Ledger::paise($input[$key]);
        }
        $input['exit_price'] = isset($input['exit_price']) ? Ledger::paise($input['exit_price']) : null;
        $input['date'] = Carbon::parse($input['date'], 'Asia/Kolkata')->utc();
        $input['state'] = $input['section'] === 'Last trades' ? 'Closed' : ($input['state'] === 'Closed' ? 'Active' : $input['state']);
        $this->ledger->change((int) $input['client_id'], function ($client) use ($position, $input) {
            $position ? $position->update($input) : Position::create($input);
            Ledger::audit('position', ($position ? 'Updated ' : 'Added ').$input['stock'].' · '.$input['section'], $client->id);
        });

        return redirect()->route('positions.index', ['category' => $input['category'], 'section' => $input['section']])->with('success', 'Position saved.');
    }

    public function positionAction(Request $request, Position $position)
    {
        $input = $request->validate(['action' => ['required', Rule::in(['hold', 'remove'])]]);
        $this->ledger->change($position->client_id, function ($client) use ($position, $input) {
            $position->refresh();
            if ($input['action'] === 'remove') {
                $position->delete();
            } else {
                if ($position->section === 'Last trades') {
                    throw ValidationException::withMessages(['position' => 'Closed trades cannot be held.']);
                }
                $position->update(['state' => $position->state === 'Hold' ? 'Active' : 'Hold']);
            }
            Ledger::audit('position', ($input['action'] === 'remove' ? 'Removed ' : 'Updated status of ').$position->stock, $client->id);
        });

        return back()->with('success', 'Position updated.');
    }

    public function transactions(Request $request)
    {
        $data = $this->common($request);
        $tab = $request->get('tab') === 'Withdrawals' ? 'Withdrawals' : 'Deposits';
        $rows = ($tab === 'Withdrawals' ? $data['allWithdrawals'] : $data['allDeposits'])->filter(fn ($r) => (! $request->filled('status') || $r->status === $request->get('status')) && (! $request->filled('client') || $r->client_id == $request->get('client')) && (! $request->filled('q') || str_contains(strtolower(($r->reference ?? 'WD-'.$r->id).' '.$r->client->name.' '.$r->client->code), strtolower($request->get('q')))));

        return view('transactions.index', $data + compact('tab', 'rows'));
    }

    public function depositForm(Request $request, ?Deposit $deposit = null)
    {
        return view('transactions.deposit', $this->common($request) + ['deposit' => $deposit]);
    }

    public function saveDeposit(Request $request, ?Deposit $deposit = null)
    {
        $input = $request->validate(['client_id' => 'required|integer|exists:clients,id', 'date' => 'required|date', 'amount' => 'required|numeric|min:0.01|max:9999999999|decimal:0,2', 'processed_by' => 'required|string|max:100', 'reference' => ['required', 'string', 'max:100', Rule::unique('deposits')->ignore($deposit?->id)], 'status' => ['required', Rule::in(['Completed', 'Pending'])]]);
        if ($deposit && $deposit->client_id !== (int) $input['client_id']) {
            throw ValidationException::withMessages(['client_id' => 'Saved deposits cannot be moved between clients.']);
        }
        $input['amount'] = Ledger::paise($input['amount']);
        $input['date'] = Carbon::parse($input['date'], 'Asia/Kolkata')->utc();
        $this->ledger->change((int) $input['client_id'], function ($client) use ($deposit, $input) {
            $deposit ? $deposit->update($input) : Deposit::create($input);
            Ledger::audit('deposit', ($deposit ? 'Updated ' : 'Recorded ').'deposit '.$input['reference'], $client->id);
        });

        return redirect()->route('transactions.index')->with('success', 'Deposit saved.');
    }

    public function removeDeposit(Deposit $deposit)
    {
        $this->ledger->change($deposit->client_id, function ($client) use ($deposit) {
            $deposit->delete();
            Ledger::audit('deposit', 'Removed deposit '.$deposit->reference, $client->id);
        });

        return back()->with('success', 'Deposit removed.');
    }

    public function withdrawalForm(Request $request)
    {
        return view('transactions.withdrawal', $this->common($request));
    }

    public function requestWithdrawal(Request $request)
    {
        $input = $request->validate(['amount' => 'required|numeric|min:0.01|max:9999999999|decimal:0,2', 'note' => 'nullable|string|max:500', 'client_id' => $request->user()->isAdmin() ? 'required|integer|exists:clients,id' : 'nullable']);
        $clientId = $request->user()->isAdmin() ? (int) $input['client_id'] : $request->user()->client->id;
        $this->ledger->change($clientId, function ($client) use ($input) {
            Withdrawal::create(['client_id' => $client->id, 'amount' => Ledger::paise($input['amount']), 'note' => $input['note'] ?? '', 'status' => 'Pending']);
            Ledger::audit('withdrawal', 'Requested a withdrawal', $client->id);
        });

        return redirect()->route('transactions.index', ['tab' => 'Withdrawals'])->with('success', 'Withdrawal requested. The amount is reserved pending review.');
    }

    public function decideWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        $input = $request->validate(['status' => ['required', Rule::in(['Approved', 'Declined'])], 'note' => 'nullable|string|max:500']);
        $this->ledger->change($withdrawal->client_id, function ($client) use ($withdrawal, $input) {
            $withdrawal->refresh();
            if ($withdrawal->status !== 'Pending') {
                throw ValidationException::withMessages(['withdrawal' => 'This request already has a decision.']);
            }
            $withdrawal->update(['status' => $input['status'], 'note' => $input['note'] ?? $withdrawal->note, 'decided_at' => now(), 'decided_by' => auth()->id()]);
            Ledger::audit('withdrawal', $input['status'].' withdrawal', $client->id);
        });

        return back()->with('success', 'Withdrawal '.strtolower($input['status']).'.');
    }

    public function profile(Request $request)
    {
        $profile = $this->selected($request);
        if (! $profile) {
            return redirect()->route('settings');
        }

        return view('profile', $this->common($request) + ['profile' => $profile->load('user', 'documents'), 'profileBalance' => $this->ledger->balance($profile), 'ownProfile' => ! $request->user()->isAdmin()]);
    }

    public function changePassword(Request $request)
    {
        $input = $request->validate(['current_password' => 'required|current_password:web', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers(), 'max:128']]);
        DB::transaction(function () use ($request, $input) {
            $request->user()->update(['password' => $input['password'], 'password_changed_at' => now(), 'remember_token' => null]);
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
            Ledger::audit('password', 'Changed account password', $request->user()->client?->id);
        });
        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully.');
    }

    public function photo(Request $request, PrivateDocuments $documents)
    {
        abort_if($request->user()->isAdmin(), 403);
        $request->validate(['photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120']);
        $documents->replace($request->file('photo'), 'photo', $request->user()->client->id);

        return back()->with('success', 'Profile photo updated.');
    }

    public function settings(Request $request)
    {
        return view('settings', $this->common($request));
    }

    public function saveSettings(Request $request, PrivateDocuments $documents)
    {
        $input = $request->validate(['name' => 'required|string|max:80', 'payment_name' => 'nullable|string|max:100', 'upi_id' => ['nullable', 'max:150', 'regex:/^[\w.\-]+@[\w.\-]+$/'], 'payment_note' => 'nullable|string|max:1000', 'qr' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120']);
        DB::transaction(function () use ($input) {
            WorkspaceSetting::current()->update(collect($input)->except('qr')->all());
            Ledger::audit('settings', 'Updated workspace and payment details');
        });
        if ($request->hasFile('qr')) {
            $documents->replace($request->file('qr'), 'qr', null);
        }

        return back()->with('success', 'Workspace settings saved.');
    }

    public function funds(Request $request)
    {
        return view('funds', $this->common($request));
    }

    public function document(Request $request, Document $document)
    {
        abort_unless($request->user()->isAdmin() || $document->kind === 'qr' || $document->client_id === $request->user()->client?->id, 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        $headers = ['Content-Type' => $document->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $response = $document->mime === 'application/pdf'
            ? Storage::disk('local')->download($document->path, $document->original_name, $headers)
            : response()->file(Storage::disk('local')->path($document->path), $headers);
        $response->setPrivate();

        return $response;
    }

    public function activity(Request $request)
    {
        return view('activity', $this->common($request));
    }

    public function coming(Request $request, string $feature)
    {
        $features = ['fixed-return' => 'Fixed return', 'reports' => 'Reports', 'rate-us' => 'Rate us', 'plans' => 'Plans', 'fixed-deposit' => 'Fixed deposit', 'future-investment' => 'Future investment'];
        abort_unless(isset($features[$feature]), 404);

        return view('coming', $this->common($request) + ['feature' => $features[$feature]]);
    }

    public function export(Request $request, string $type)
    {
        $data = $this->common($request);
        $rows = match ($type) {
            'clients' => $data['clients']->map(fn ($c) => [$c->code, $c->name, $c->user->email, $c->phone, $c->pan, $c->investment / 100, $c->risk]),
            'positions' => $data['allPositions']->map(fn ($p) => [$p->client->code, $p->category, $p->section, $p->stock, $p->date->toIso8601String(), $p->side, $p->quantity, $p->ltp / 100, $p->pnl / 100, $p->state]),
            'deposits' => $data['allDeposits']->map(fn ($d) => [$d->client->code, $d->date->toIso8601String(), $d->reference, $d->amount / 100, $d->status]),
            'withdrawals' => $data['allWithdrawals']->map(fn ($w) => [$w->client->code, $w->created_at->toIso8601String(), $w->amount / 100, $w->status, $w->note]),
            default => abort(404),
        };
        $headers = match ($type) {
            'clients' => ['Client ID', 'Name', 'Email', 'Phone', 'PAN', 'Opening investment (INR)', 'Profile'],
            'positions' => ['Client ID', 'Category', 'Section', 'Instrument', 'Date', 'Side', 'Quantity', 'LTP (INR)', 'P&L (INR)', 'Status'],
            'deposits' => ['Client ID', 'Date', 'Transaction ID', 'Amount (INR)', 'Status'],
            'withdrawals' => ['Client ID', 'Date', 'Amount (INR)', 'Status', 'Note'],
        };

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, $row));
            }
            fclose($handle);
        }, 'wealthdesk-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
