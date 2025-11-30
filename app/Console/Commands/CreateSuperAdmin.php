<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:superadmin {--name=} {--email=} {--password=} {--tenant_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a super admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->option('name') ?: $this->ask('What is the superadmin name?');
        $email = $this->option('email') ?: $this->ask('What is the superadmin email?');
        $password = $this->option('password') ?: $this->secret('What is the superadmin password?');


        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");
            return 1;
        }

        // Create the super admin user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN, // Make sure this constant is defined in your User model
            'registered_at' => now(),
            'status' => 'active',
        ]);

        $this->info("Superadmin created successfully: {$user->name} ({$user->email})");

        return Command::SUCCESS;
    }
}
