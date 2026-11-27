<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CreateUser extends Command
{
    protected $signature = 'infrawatch:user {email} {--role=viewer}';

    protected $description = 'Create a user with a hidden interactive password prompt';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->option('role');
        $password = $this->secret('Password (minimum 14 characters)');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($role, ['admin', 'viewer']) || strlen($password ?? '') < 14) {
            $this->error('Invalid email, role or password length.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('User already exists.');

            return self::FAILURE;
        }
        $u = new User;
        $u->name = $email;
        $u->email = $email;
        $u->role = $role;
        $u->password = $password;
        try {
            DB::transaction(function () use ($u, $role) {
                $u->save();
                Audit::record(null, 'user.created', $u->id, ['role' => $role]);
            });
        } catch (\Throwable) {
            $this->error('User creation failed. Check database health.');

            return self::FAILURE;
        }
        $this->info('User created.');

        return self::SUCCESS;
    }
}
