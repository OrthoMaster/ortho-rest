<?php

namespace App\Console\Commands;

use App\Push\WebPush;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('liresto:vapid')]
#[Description('Tạo cặp khoá VAPID cho thông báo đẩy, chép vào .env (chỉ làm một lần)')]
class MakeVapidKeys extends Command
{
    public function handle(): int
    {
        [$private, $public] = WebPush::newKeyPair();

        $this->line('VAPID_PUBLIC_KEY='.WebPush::encode($public));
        $this->line('VAPID_PRIVATE_KEY='.WebPush::encode($private));
        $this->newLine();
        $this->comment('Chép hai dòng trên vào .env. Đổi khoá thì mọi máy phải bật thông báo lại.');

        return self::SUCCESS;
    }
}
