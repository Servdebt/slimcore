<?php

namespace Servdebt\SlimCore\ServiceProviders;

use Servdebt\SlimCore\App;
use Servdebt\SlimCore\Filesystem\Filesystem as ExtendedFilesystem;
use Servdebt\SlimCore\Filesystem\S3\AsyncAwsS3Adapter;

class Filesystem implements ProviderInterface
{
    public static function register(App $app, string $serviceName, array $settings = [])
    {
        $app->registerInContainer($serviceName, function() use ($settings) {
            return function($configsOverride = []) use ($settings) {

                $configs = array_merge($settings, $configsOverride);

                $filesystem = null;
                switch ($configs['driver']) {
                    case 'local':
                        $filesystem = self::createLocal($configs);
                        break;

                    case 'ftp':
                        $filesystem = self::createFtp($configs);
                        break;

                    case 's3Async':
                        $filesystem = self::createS3Async($configs);
                        break;

                    default:
                        throw new \Exception("Filesystem driver {$configs['driver']} not found");
                        break;
                }

                return $filesystem;
            };
        });
    }

    public static function createLocal($configs)
    {
        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($configs['root']);

        return new ExtendedFilesystem($adapter, [], null);
    }

    public static function createFtp($configs)
    {
        $ftpOptions = \League\Flysystem\Ftp\FtpConnectionOptions::fromArray($configs);
        $adapter = new \League\Flysystem\Ftp\FtpAdapter($ftpOptions);

        return new ExtendedFilesystem($adapter, [], null);
    }

    public static function createS3Async($settings)
    {
        $client = new \AsyncAws\SimpleS3\SimpleS3Client([
            'endpoint'          => $settings['endpoint'],
            'accessKeyId'       => $settings['key'],
            'accessKeySecret'   => $settings['secret'],
            'region'            => $settings['region'],
            'pathStyleEndpoint' => true,
        ]);

        $adapter = new AsyncAwsS3Adapter($client, $settings['bucket'], $settings['prefix'] ?? '', null, null);

        return new ExtendedFilesystem($adapter, [], null);
    }
}
