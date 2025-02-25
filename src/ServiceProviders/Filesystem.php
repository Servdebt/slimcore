<?php

namespace Servdebt\SlimCore\ServiceProviders;

use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use Servdebt\SlimCore\App;
use Servdebt\SlimCore\Filesystem\Filesystem as ExtendedFilesystem;
use Servdebt\SlimCore\Filesystem\S3\AsyncAwsS3Adapter;
use AsyncAws\SimpleS3\SimpleS3Client;

class Filesystem implements ProviderInterface
{
    public static function register(App $app, string $serviceName, array $settings = []): void
    {
        $app->registerInContainer($serviceName, function($configsOverride = []) use ($settings) {
            $configs = array_merge($settings, $configsOverride);

            $filesystem = match ($configs['driver']) {
                'local' => self::createLocal($configs),
                'ftp'   => self::createFtp($configs),
                'sftp'  => self::createSftp($configs),
                's3Async' => self::createS3Async($configs),
                default => null,
            };

            if ($filesystem == null) {
                throw new \Exception("Filesystem driver {$configs['driver']} not found");
            }

            return $filesystem;
        });
    }

    public static function createLocal($configs): ExtendedFilesystem
    {
        $adapter = new LocalFilesystemAdapter($configs['root']);

        return new ExtendedFilesystem($adapter, [], null);
    }

    public static function createFtp($configs): ExtendedFilesystem
    {
        $ftpOptions = FtpConnectionOptions::fromArray($configs);
        $adapter = new FtpAdapter($ftpOptions);

        return new ExtendedFilesystem($adapter, [], null);
    }

    public static function createSftp($configs): ExtendedFilesystem
    {
        $adapter = new SftpAdapter(
            new SftpConnectionProvider(
                $configs['host'],
                $configs['username'],
                $configs['password'],
                $configs['privateKeyPath'] ?? null,
                $configs['privateKeyPass'] ?? null,
                $configs['port'] ?? 22,
                false,
                $configs['timeout'] ?? 10,
                $configs['maxTries'] ?? 4,
            ),
            $configs['root']
        );

        return new ExtendedFilesystem($adapter, [], null);
    }

    public static function createS3Async($settings): ExtendedFilesystem
    {
        $client = new SimpleS3Client([
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
