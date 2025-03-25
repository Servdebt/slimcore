<?php

namespace Servdebt\SlimCore\ServiceProviders;
use PHPMailer\PHPMailer\PHPMailer;
use Servdebt\SlimCore\App;

class Mailer implements ProviderInterface
{

    public static function register(App $app, $serviceName, array $settings = []): void
    {
        $app->registerInContainer($serviceName, function($configsOverride = []) use ($settings) {
            $configs = array_merge($settings, $configsOverride);

            $mail = new PHPMailer;
            $mail->CharSet = "UTF-8";
            $mail->isSMTP();
            $mail->isHTML(true);
            $mail->Host = $configs['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $configs['username'];
            $mail->Password = $configs['password'];
            $mail->SMTPSecure = $configs['secure'];
            $mail->Port = $configs['port'];

            $mail->setFrom($configs['from'], $configs['fromName']);

            return $mail;
        });
    }

}