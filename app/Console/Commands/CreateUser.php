<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    protected $signature = 'user:create
        {email : Email user}
        {--name= : Nama user}
        {--password= : Password (optional)}';

    protected $description = 'Create user manually via artisan command';

    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? 'User';
        $password = $this->option('password')
            ?? $this->secret('Password (kosong = auto generate)');
        $password = $password ?: str()->random(12);

        if (User::where('email', $email)->exists()) {
            $this->error('User dengan email tersebut sudah ada.');
            return Command::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $this->info('User created successfully');
        $this->line('-----------------------------------');
        $this->line("Email    : {$email}");
        $this->line("Password : {$password}");
        $this->line("Token    : {$token}");
        $this->line('-----------------------------------');
        $this->warn('Save this token securely. It will not be shown again.');

        return Command::SUCCESS;
    }
}
