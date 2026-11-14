<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đặt bàn không còn chọn khu vực: bỏ biến {{khu_vuc}} khỏi các mẫu email quán đã sửa.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mail_templates')->where('body', 'like', '%{{khu_vuc}}%')->orWhere('subject', 'like', '%{{khu_vuc}}%')
            ->orderBy('id')->each(function (object $template): void {
                $strip = fn (string $text) => str_replace(['người, {{khu_vuc}}', ', {{khu_vuc}}', '{{khu_vuc}}'], ['người', '', ''], $text);

                DB::table('mail_templates')->where('id', $template->id)->update([
                    'subject' => $strip((string) $template->subject),
                    'body' => $strip((string) $template->body),
                ]);
            });
    }

    public function down(): void
    {
        // Không khôi phục được nội dung cũ, và cũng không cần.
    }
};
