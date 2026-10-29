<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email?} {password?}';
    protected $description = 'Reset admin or any user password';

    public function handle(): int
    {
        $email = $this->argument('email') ?? 'admin@example.com';
        $password = $this->argument('password') ?? '123456';

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return 1;
        }

        // User model has 'password' => 'hashed' cast, so plain text is auto-hashed
        $user->update(['password' => $password]);

        $this->info("Password reset for {$email} (id={$user->id}, role={$user->role})");
        $this->info("New password: {$password}");

        return 0;
    }
}
