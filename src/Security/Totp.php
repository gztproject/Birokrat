<?php

namespace App\Security;

final class Totp
{
    public static function generateSecret(int $length = 20): string
    {
        return self::base32Encode(random_bytes($length));
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $timeSlice = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; ++$i) {
            if (hash_equals(self::at($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function provisioningUri(string $secret, string $account, string $issuer = 'Birokrat'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
        );
    }

    public static function qrCodeDataUri(string $uri): string
    {
        $barcode = new \TCPDF2DBarcode($uri, 'QRCODE,M');
        $svg = $barcode->getBarcodeSVGcode(6, 6, 'black');

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public static function at(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $binary = str_pad($binary, (int) (ceil(strlen($binary) / 5) * 5), '0');
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $binary = '';
        foreach (str_split($secret) as $char) {
            $binary .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
