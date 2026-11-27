<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo seed is disabled in production.');
        }
        $password = config('infrawatch.demo_password');
        if (! is_string($password) || strlen($password) < 14) {
            throw new \RuntimeException('Set a demo password of at least 14 characters.');
        }
        foreach (['admin', 'viewer'] as $role) {
            if (User::where('email', $role.'@infrawatch.test')->exists()) {
                continue;
            }
            $u = new User;
            $u->name = ucfirst($role);
            $u->email = $role.'@infrawatch.test';
            $u->role = $role;
            $u->password = $password;
            $u->save();
            Audit::record(null, 'demo.user.created', $u->id, ['role' => $role]);
        }
        if (! DB::table('services')->exists() && in_array('example.com', config('infrawatch.hosts'), true)) {
            $id = DB::table('services')->insertGetId(['name' => 'Example website', 'url' => 'https://example.com/', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record(null, 'demo.service.created', $id);
        }
        // No synthetic availability data: checks only appear after a real monitoring pass.
    }
}
