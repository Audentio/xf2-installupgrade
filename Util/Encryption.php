<?php

namespace ThemeHouse\InstallAndUpgrade\Util;

class Encryption
{
    const ENCTYPE = 'aes-256-cbc';

    /**
     * @param $red
     * @param $secret
     * @return string
     */
    public static function encryptString($red, $secret)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCTYPE));
        $black = openssl_encrypt($red, self::ENCTYPE, $secret, 0, $iv);

        return base64_encode($black) . ':' . base64_encode($iv);
    }

    /**
     * @param $blackWithIV
     * @param $secret
     * @return bool|string
     */
    public static function decryptString($blackWithIV, $secret)
    {
        $parts = explode(':', $blackWithIV);
        if (count($parts) < 2) {
            return false;
        }
        $black = base64_decode($parts[0]);
        $iv = base64_decode($parts[1]);

        return openssl_decrypt($black, self::ENCTYPE, $secret, 0, $iv);
    }
}
