<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    public function test_every_translation_key_used_in_code_exists_in_vietnamese(): void
    {
        $files = [...File::allFiles(resource_path('views')), ...File::allFiles(app_path())];
        $missing = [];

        foreach ($files as $file) {
            preg_match_all("/__\('((?:ui|modules|auth|passwords|pagination)\.[a-z0-9_.]*[a-z0-9_])'/", $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                if (! Lang::has($key, 'vi', false)) {
                    $missing[] = "$key ({$file->getFilename()})";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_user_locale_defaults_to_vietnamese(): void
    {
        $this->assertSame('vi', app()->getLocale());
    }
}
