<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\Position;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WorkspaceSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo accounts are only allowed in local or testing environments.');
        }
        if (User::exists()) {
            throw new \RuntimeException('Demo data requires an empty database. Existing accounts will not be overwritten.');
        }
        DB::transaction(function () {
            $admin = User::create(['name' => 'Administrator', 'email' => 'admin@wealthdesk.test', 'password' => 'Admin@12345!', 'role' => 'admin']);
            WorkspaceSetting::current();
            $names = [['Aarav', 'Sharma', 'Medium', 250000], ['Priya', 'Mehta', 'Low', 500000], ['Rohan', 'Kapoor', 'High', 175000], ['Ananya', 'Patel', 'Medium', 350000], ['Vikram', 'Singh', 'Advanced', 800000], ['Neha', 'Gupta', 'Low', 225000]];
            $categories = ['Equity', 'Equity', 'Futures & Options', 'Commodity', 'Forex & Currency', 'Equity'];
            $instruments = ['RELIANCE', 'HDFCBANK', 'NIFTY 50 OCT FUT', 'GOLD OCT', 'EUR / INR', 'INFY'];
            foreach ($names as $i => [$first,$surname,$risk,$investment]) {
                $user = User::create(['name' => "$first $surname", 'email' => $i === 0 ? 'client@wealthdesk.test' : 'sample'.($i + 1).'@wealthdesk.test', 'password' => 'Client@12345!', 'role' => 'client']);
                $client = Client::create(['user_id' => $user->id, 'code' => 'DEMO'.($i + 1).'A', 'phone' => '900000000'.$i, 'risk' => $risk, 'investment' => $investment * 100, 'sample' => true, 'details' => ['first_name' => $first, 'surname' => $surname, 'bank_name' => 'Sample Bank', 'account_type' => 'Savings', 'account_holder' => "$first $surname", 'account_number' => '12345678'.($i + 1), 'ifsc' => 'DEMO0123456', 'nominee' => 'Parent', 'nominee_name' => 'Sample Nominee']]);
                for ($m = 0; $m < 6; $m++) {
                    $date = now()->startOfMonth()->subMonths(5 - $m)->addDays(4 + $i);
                    Deposit::create(['client_id' => $client->id, 'date' => $date, 'amount' => (10000 + $i * 2500 + $m * 1000) * 100, 'processed_by' => 'Sample advisor', 'reference' => "DEMO-$i-$m", 'status' => $m === 5 && $i === 2 ? 'Pending' : 'Completed']);
                }
                Position::create(['client_id' => $client->id, 'category' => $categories[$i], 'section' => 'Current positions', 'stock' => $instruments[$i], 'date' => now(), 'side' => $i === 4 ? 'Sell' : 'Buy', 'quantity' => [100, 150, 50, 10, 100, 200][$i], 'entry_price' => [280000, 168000, 2550000, 7800000, 9200, 182000][$i], 'ltp' => [284200, 170100, 2565000, 7820000, 9100, 181500][$i], 'pnl' => [420000, 315000, 750000, 200000, 10000, -100000][$i], 'state' => $i === 3 ? 'Hold' : 'Active']);
                Position::create(['client_id' => $client->id, 'category' => 'Equity', 'section' => 'Last trades', 'stock' => ['TCS', 'ITC', 'SBIN', 'WIPRO', 'TATAMOTORS', 'AXISBANK'][$i], 'date' => now()->subDays(7 + $i), 'side' => 'Buy', 'quantity' => 100, 'entry_price' => 100000, 'ltp' => 105000, 'exit_price' => 105000, 'pnl' => (5000 + $i * 1500) * 100, 'state' => 'Closed']);
                if ($i === 0) {
                    Withdrawal::create(['client_id' => $client->id, 'amount' => 1500000, 'note' => 'Sample withdrawal request', 'status' => 'Pending']);
                }
            }
            AuditEvent::create(['actor_id' => $admin->id, 'action' => 'sample', 'description' => 'Loaded fictional sample clients and activity']);
        });
    }
}
