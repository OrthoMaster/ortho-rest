<?php

namespace App\Push;

use App\Models\PushSubscription;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Gửi thông báo đẩy (Web Push) tới trình duyệt, không cần thư viện ngoài:
 * mã hoá nội dung theo RFC 8291 (aes128gcm) và ký VAPID theo RFC 8292 (ES256).
 * Chưa khai khoá VAPID trong .env thì không gửi gì.
 */
class WebPush
{
    /** Phần đầu DER của khoá công khai P-256 (SubjectPublicKeyInfo), sau đó là 65 byte điểm. */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function enabled(): bool
    {
        return filled(config('liresto.push.public_key')) && filled(config('liresto.push.private_key'));
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $message
     */
    public function send(Collection $subscriptions, array $message): void
    {
        if (! self::enabled() || $subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $requests = $subscriptions->mapWithKeys(fn (PushSubscription $subscription) => [$subscription->id => [
            'url' => $subscription->endpoint,
            'body' => $this->encrypt($payload, self::decode($subscription->public_key), self::decode($subscription->auth_token)),
            'headers' => [
                'Authorization' => $this->authorization($subscription->endpoint),
                'Content-Encoding' => 'aes128gcm',
                'Content-Type' => 'application/octet-stream',
                // Báo món cũ quá 10 phút thì không còn ý nghĩa.
                'TTL' => '600',
                'Urgency' => 'high',
            ],
        ]]);

        $responses = Http::pool(fn (Pool $pool) => $requests->map(fn (array $request, int $id) => $pool->as((string) $id)
            ->timeout(5)->withHeaders($request['headers'])->withBody($request['body'], 'application/octet-stream')->post($request['url']))->all());

        // Máy đã gỡ app hoặc tắt thông báo: dịch vụ đẩy trả 404 / 410, xoá đăng ký.
        $gone = collect($responses)->filter(fn ($response, $id) => $response instanceof Response && in_array($response->status(), [404, 410], true))->keys();

        if ($gone->isNotEmpty()) {
            PushSubscription::withoutGlobalScopes()->whereKey($gone->all())->delete();
        }

        // Lỗi khác (khoá VAPID sai, Apple từ chối, không kết nối được...): ghi log để tìm nguyên nhân.
        collect($responses)->each(function ($response, $id) use ($gone): void {
            if ($gone->contains($id) || ($response instanceof Response && $response->successful())) {
                return;
            }

            Log::warning('Web push failed', [
                'subscription' => $id,
                'status' => $response instanceof Response ? $response->status() : null,
                'error' => $response instanceof Response ? mb_substr($response->body(), 0, 300) : ($response instanceof Throwable ? $response->getMessage() : null),
            ]);
        });
    }

    /**
     * Mã hoá aes128gcm (RFC 8291). $asKey (khoá riêng, khoá công khai) và $salt chỉ truyền khi kiểm thử.
     *
     * @param  array{0: string, 1: string}|null  $asKey
     */
    public function encrypt(string $plaintext, string $uaPublic, string $authSecret, ?array $asKey = null, ?string $salt = null): string
    {
        [$asPrivate, $asPublic] = $asKey ?? self::newKeyPair();
        $salt ??= random_bytes(16);

        $shared = openssl_pkey_derive(self::publicKey($uaPublic), self::privateKey($asPrivate, $asPublic), 32);

        if ($shared === false) {
            throw new RuntimeException('ECDH failed');
        }

        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $ciphertext = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        return $salt.pack('N', 4096).chr(strlen($asPublic)).$asPublic.$ciphertext.$tag;
    }

    /** Header Authorization VAPID cho một địa chỉ đẩy. */
    public function authorization(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $audience = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $public = config('liresto.push.public_key');

        $unsigned = self::encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])).'.'.self::encode(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => config('liresto.push.subject'),
        ], JSON_UNESCAPED_SLASHES));

        openssl_sign($unsigned, $der, self::privateKey(self::decode(config('liresto.push.private_key')), self::decode($public)), OPENSSL_ALGO_SHA256);

        return 'vapid t='.$unsigned.'.'.self::encode(self::rawSignature($der)).', k='.$public;
    }

    /** @return array{0: string, 1: string} khoá riêng 32 byte, khoá công khai 65 byte */
    public static function newKeyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];

        return [self::pad($ec['d']), "\x04".self::pad($ec['x']).self::pad($ec['y'])];
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }

    private static function publicKey(string $point): \OpenSSLAsymmetricKey
    {
        $der = hex2bin(self::SPKI_PREFIX).$point;

        return openssl_pkey_get_public(self::pem('PUBLIC KEY', $der)) ?: throw new RuntimeException('Invalid public key');
    }

    private static function privateKey(string $private, string $public): \OpenSSLAsymmetricKey
    {
        // ECPrivateKey (SEC1): version 1, khoá riêng, tên đường cong P-256, khoá công khai.
        $der = hex2bin('30770201010420').$private.hex2bin('a00a06082a8648ce3d030107a144034200').$public;

        return openssl_pkey_get_private(self::pem('EC PRIVATE KEY', $der)) ?: throw new RuntimeException('Invalid private key');
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN $label-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END $label-----\n";
    }

    /** Chữ ký DER (SEQUENCE của hai INTEGER) thành 64 byte r || s như JWT cần. */
    private static function rawSignature(string $der): string
    {
        $offset = ord($der[1]) & 0x80 ? 3 : 2;
        $parts = [];

        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $parts[] = self::pad(ltrim(substr($der, $offset + 2, $length), "\0"));
            $offset += 2 + $length;
        }

        return $parts[0].$parts[1];
    }

    private static function pad(string $bytes): string
    {
        return str_pad($bytes, 32, "\0", STR_PAD_LEFT);
    }
}
