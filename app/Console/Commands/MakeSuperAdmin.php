<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('liresto:super-admin {email} {--name=Super admin}')]
#[Description('Tạo tài khoản super admin, dùng khi chưa có seed (production)')]
class MakeSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error('Email này đã có tài khoản.');

            return self::FAILURE;
        }

        $user = new User([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => $this->secret('Mật khẩu'),
        ]);
        $user->is_super_admin = true;
        $user->save();

        $this->info("Đã tạo super admin {$user->email}.");

        return self::SUCCESS;
    }
}
