<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Creates or promotes an administrator from the command line.
 *
 * The recovery path when someone locks themselves out: a buyer with SSH or a
 * hosting terminal can always get back in without touching the database.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'cms:admin
                            {--name= : The display name}
                            {--email= : The email address}
                            {--password= : The password (prompted if omitted)}';

    protected $description = 'Create a new admin account, or promote an existing user to admin';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:190'],
                'password' => ['required', Password::min(10)->letters()->numbers()->mixedCase()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing) {
            if (! $this->confirm("An account already exists for {$email}. Promote it to admin and reset its password?", true)) {
                return self::FAILURE;
            }

            $existing->restore();
            $existing->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
            ])->save();

            $this->info("{$email} is now an administrator.");

            return self::SUCCESS;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->info("Admin account created for {$email}.");
        $this->line('Sign in at '.route('admin.login'));
        $this->newLine();
        $this->comment('Turn on two-factor authentication from your profile as soon as you sign in.');

        return self::SUCCESS;
    }
}
