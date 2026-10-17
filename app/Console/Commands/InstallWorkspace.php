<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WorkspaceSetting;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallWorkspace extends Command
{
    protected $signature = 'wealthdesk:install {--demo : Install fictional local demo accounts and records}';

    protected $description = 'Create the first administrator or install local demonstration data';

    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->error('An administrator already exists. Existing credentials were not changed.');

            return self::FAILURE;
        }
        if ($this->option('demo')) {
            if (! app()->environment('local')) {
                $this->error('Demo installation is restricted to APP_ENV=local.');

                return self::FAILURE;
            }
            $this->call('db:seed', ['--class' => DemoSeeder::class]);
            $this->info('Local demo workspace installed.');
            $this->table(['Role', 'Email', 'Password'], [['Admin', 'admin@wealthdesk.test', 'Admin@12345!'], ['Client', 'client@wealthdesk.test', 'Client@12345!']]);

            return self::SUCCESS;
        }
        $data = ['name' => $this->ask('Administrator name'), 'email' => strtolower($this->ask('Administrator email')), 'password' => $this->secret('Password (10+ characters, including a letter and a number)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:100', 'email' => 'required|email|unique:users,email', 'password' => ['required', 'max:128', Password::min(10)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

return self::FAILURE;
        }
        User::create($data + ['role' => 'admin']);
        WorkspaceSetting::current();
        $this->info('Administrator created. Sign in at /login.');

        return self::SUCCESS;
    }
}
