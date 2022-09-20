<?php

namespace Servdebt\SlimCore\Utils;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;

class Security
{

    public static function encrypt(string $str, string $secretKey) :string
    {
        return Crypto::encrypt($str, Key::loadFromAsciiSafeString($secretKey));
    }

    public static function decrypt(string $str, string $secretKey) :string
    {
        try {
            return Crypto::decrypt($str, Key::loadFromAsciiSafeString($secretKey));

        } catch (WrongKeyOrModifiedCiphertextException $e) {
            return $str;
        }
    }
    
}