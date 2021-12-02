<?php

namespace Servdebt\SlimCore\App\Console;

use Servdebt\SlimCore\App\Console\Command as BaseCommand;

class SecureDotEnv extends BaseCommand
{
    private $cipher = "camellia-256-cfb8";

    /**
     * @param string $sourceFile
     * @param string $outputFile
     * @param string $secret
     * @return string
     */
	public function reveal(string $sourceFile = ".env.hide", string $outputFile = ".env.reveal", string $secret = '')
    {
        // verify if input file exists
        if (! file_exists($sourceFile)) {
            return "File not found: " . $sourceFile;
        }

        // open an encrypted file (default: .env.hide)
        $fileContent = base64_decode(file_get_contents($sourceFile));
        
        // the first block, before :: is a number - the hash_size
        $pointer = 0;
        $separatorPos = strpos($fileContent, "::");
        $hashSize = substr($fileContent, $pointer, $separatorPos);
        
        // the second block is the hash of the secret content
        $pointer = $separatorPos + 2;
        $hash = substr($fileContent, $pointer, $hashSize);
        
        // the third block is the iv
        $pointer += $hashSize;
        $vectorSize = openssl_cipher_iv_length($this->cipher);
        $vector = substr($fileContent, $pointer, $vectorSize);

        // the remain data is the encrypted secret content
        $pointer += $vectorSize;
        $encryptedData = substr($fileContent, $pointer);

        // decrypt it using a password,
        if (! $secret) {
            $secret = $this->askSecret();
        }
        $key = sha1($secret);
        $secret = "";
        $decryptedData = openssl_decrypt($encryptedData, $this->cipher, $key, $options=0, $vector);
        $key = "";

        if (sha1($decryptedData) != $hash) {
            return "Error: wrong secret?";
        }

        // save it to open file (default: .env.reveal)
        file_put_contents($outputFile, $decryptedData);

        return "Done";
    }


    /**
     * @param string $sourceFile
     * @param string $outputFile
     * @param string $secret
     * @return string
     */
    public function hide(string $sourceFile = ".env", string $outputFile = ".env.hide", string $secret = '')
    {
        if (! file_exists($sourceFile)) {
            return "File not found: " . $sourceFile;
        }

        // open a readable file (default: .env)
        $data = file_get_contents($sourceFile);
        $hash = sha1($data);
        $hashSize = strlen($hash);
        
        // encrypt it
        $vectorSize = openssl_cipher_iv_length($this->cipher);
        $vector = openssl_random_pseudo_bytes($vectorSize);

        if (! $secret) {
            $secret = $this->askSecret();
        }
        $key = sha1($secret);
        $secret = null;
        $encryptedData = openssl_encrypt($data, $this->cipher, $key, $options=0, $vector);
        $key = null;
        
        // save it to encrypted file (default: .env.hide)
        file_put_contents($outputFile, base64_encode($hashSize . "::" . $hash . $vector . $encryptedData));
        
        return "Done";
    }


    private function askSecret()
    {
        // ask password
        echo "Password: ";
        system('stty -echo');
        return trim(fgets(STDIN));
    }
}
