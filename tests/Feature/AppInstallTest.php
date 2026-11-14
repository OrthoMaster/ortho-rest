<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\Restaurant;
use App\Models\User;
use App\Push\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Ứng dụng cài được của quán (PWA) và đăng ký thông báo đẩy. */
class AppInstallTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['name' => 'Quán Mây']);
        $this->enableModules($this->restaurant, 'menu', 'kitchen');
        $this->owner = User::factory()->owner($this->restaurant)->create();
    }

    public function test_login_page_offers_the_app_of_the_restaurant(): void
    {
        $slug = $this->restaurant->slug;

        $this->get("/$slug/login")->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-app-install', false)
            ->assertSee(__('push.install'))
            ->assertSee('id="app-install-ios"', false);

        $this->get("/$slug/app.webmanifest")->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'Quán Mây')
            ->assertJsonPath('start_url', "/$slug/admin")
            ->assertJsonPath('scope', "/$slug/")
            ->assertJsonPath('display', 'standalone')
            ->assertJsonCount(2, 'icons');
    }

    public function test_icon_uses_the_logo_or_the_default(): void
    {
        $slug = $this->restaurant->slug;
        $default = $this->get("/$slug/app-icon-192.png")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame([192, 192], array_slice(getimagesizefromstring($default->getContent()), 0, 2));

        Storage::fake('public');
        $logo = UploadedFile::fake()->image('logo.png', 300, 120);
        $this->restaurant->update(['theme' => ['logo' => $logo->store('logos', 'public')]]);
        $icon = $this->get("/$slug/app-icon-512.png")->assertOk();
        $this->assertSame([512, 512], array_slice(getimagesizefromstring($icon->getContent()), 0, 2));
        $this->assertNotSame($default->getContent(), $icon->getContent());

        $this->get("/$slug/app-icon-64.png")->assertNotFound();
    }

    public function test_device_subscribes_and_stops_on_logout(): void
    {
        config(['liresto.push.public_key' => 'BPub', 'liresto.push.private_key' => 'priv']);
        $url = "/{$this->restaurant->slug}/admin/push";
        $subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'BKey', 'auth' => 'secret']];

        $this->actingAs($this->owner)->get("/{$this->restaurant->slug}/admin")->assertSee('data-push', false)->assertSee('data-key="BPub"', false);
        $this->postJson($url, $subscription)->assertOk();
        $this->postJson($url, $subscription)->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->postJson($url, ['endpoint' => 'http://evil.test', 'keys' => ['p256dh' => 'x', 'auth' => 'y']])->assertUnprocessable();

        // Người khác đăng nhập trên cùng máy: máy chuyển sang người đó.
        $staff = $this->member($this->restaurant);
        $this->actingAs($staff)->postJson($url, $subscription)->assertOk();
        $this->assertSame($staff->id, PushSubscription::sole()->user_id);

        $this->post('/logout');
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_profile_sends_a_test_notification_to_own_devices(): void
    {
        $profile = "/{$this->restaurant->slug}/admin/profile";
        $test = "/{$this->restaurant->slug}/admin/push/test";
        // Chưa có khoá VAPID: báo máy chủ chưa cài.
        $this->actingAs($this->owner)->get($profile)->assertSee(__('push.test_no_keys'));

        config(['liresto.push.public_key' => 'BPub', 'liresto.push.private_key' => 'priv']);
        $sent = new \ArrayObject;
        $this->app->instance(WebPush::class, new class($sent) extends WebPush
        {
            public function __construct(private \ArrayObject $log) {}

            public function send(Collection $subscriptions, array $message): void
            {
                $this->log[] = ['users' => $subscriptions->pluck('user_id')->all(), ...$message];
            }
        });

        $this->get($profile)->assertSee(__('push.test_no_device'))->assertDontSee(__('push.test'));
        $this->from($profile)->post($test)->assertSessionHas('error', __('push.test_no_device'));

        PushSubscription::create(['user_id' => $this->owner->id, 'endpoint' => 'https://push.test/1', 'endpoint_hash' => hash('sha256', '1'), 'public_key' => 'k', 'auth_token' => 'a']);
        $this->get($profile)->assertSee(trans_choice('push.profile_devices', 1, ['count' => 1]))->assertSee(__('push.test'));
        $this->from($profile)->post($test)->assertSessionHas('status');
        $this->assertSame([$this->owner->id], $sent[0]['users']);
        $this->assertSame(__('push.test_title'), $sent[0]['title']);
    }

    public function test_banner_needs_keys_and_shows_for_any_module(): void
    {
        $this->actingAs($this->owner)->get("/{$this->restaurant->slug}/admin")->assertOk()
            ->assertSee('rel="manifest"', false)->assertDontSee('data-push', false);

        config(['liresto.push.public_key' => 'BPub', 'liresto.push.private_key' => 'priv']);
        $other = Restaurant::factory()->create();
        $this->enableModules($other, 'menu');
        // Mọi quán đều nhận thông báo (đơn xin nghỉ, đặt bàn...), không chỉ quán có Màn hình khu vực.
        $this->actingAs(User::factory()->owner($other)->create())->get("/{$other->slug}/admin")->assertOk()->assertSee('data-push', false);
    }

    /** Mã hoá đúng ví dụ mẫu trong RFC 8291, phụ lục A; chữ ký VAPID kiểm tra được bằng khoá công khai. */
    public function test_encryption_matches_the_rfc_and_vapid_is_signed(): void
    {
        $push = new WebPush;
        $body = $push->encrypt(
            'When I grow up, I want to be a watermelon',
            WebPush::decode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            WebPush::decode('BTBZMqHH6r4Tts7J_aSIgg'),
            [WebPush::decode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'), WebPush::decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8')],
            WebPush::decode('DGv6ra1nlYgDCS1FRnbzlw'),
        );
        $this->assertSame('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN', WebPush::encode($body));

        [$private, $public] = WebPush::newKeyPair();
        config(['liresto.push.public_key' => WebPush::encode($public), 'liresto.push.private_key' => WebPush::encode($private), 'liresto.push.subject' => 'mailto:a@b.vn']);
        $this->assertMatchesRegularExpression('/^vapid t=[\w-]+\.[\w-]+\.[\w-]+, k=[\w-]+$/', $header = $push->authorization('https://fcm.googleapis.com/fcm/send/abc'));

        preg_match('/t=([^,]+)/', $header, $match);
        [$head, $claims, $signature] = explode('.', $match[1]);
        $this->assertSame('https://fcm.googleapis.com', json_decode(WebPush::decode($claims), true)['aud']);
        $raw = WebPush::decode($signature);
        $integer = fn (string $bytes) => (ord(ltrim($bytes, "\0")[0]) & 0x80 ? "\0" : '').ltrim($bytes, "\0");
        [$r, $s] = [$integer(substr($raw, 0, 32)), $integer(substr($raw, 32))];
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $key = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$public), 64, "\n")."-----END PUBLIC KEY-----\n");
        $this->assertSame(1, openssl_verify("$head.$claims", $der, $key, OPENSSL_ALGO_SHA256));
    }
}
