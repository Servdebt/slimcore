<?php

namespace Servdebt\SlimCore\Filesystem\S3;

use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\VisibilityConverter;
use League\MimeTypeDetection\MimeTypeDetector;

class AsyncAwsS3Adapter extends \League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter
{
    protected S3Client $client;
    protected string $bucket;
    protected string $prefix;

    public const AVAILABLE_OPTIONS = [
        'ACL',
        'CacheControl',
        'ContentDisposition',
        'ContentEncoding',
        'ContentLength',
        'ContentType',
        'Expires',
        'GrantFullControl',
        'GrantRead',
        'GrantReadACP',
        'GrantWriteACP',
        'Metadata',
        'RequestPayer',
        'SSECustomerAlgorithm',
        'SSECustomerKey',
        'SSECustomerKeyMD5',
        'SSEKMSKeyId',
        'ServerSideEncryption',
        'StorageClass',
        'Tagging',
        'WebsiteRedirectLocation',
    ];

    /**
     * @var string[]
     */
    public const EXTRA_METADATA_FIELDS = [
        'Metadata',
        'StorageClass',
        'ETag',
        'VersionId',
    ];

    /**
     * * Important to validade if construct changes during package upgrades.
     *
     * @param S3Client $client
     */
    public function __construct(
        S3Client $client,
        string $bucket,
        string $prefix = '',
        VisibilityConverter $visibility = null,
        MimeTypeDetector $mimeTypeDetector = null
    ) {
        parent::__construct($client, $bucket, $prefix, $visibility, $mimeTypeDetector);

        $this->client = $client;
        $this->bucket = $bucket;
        $this->prefix = $prefix;
    }

    public function bulkDelete(array $files): bool
    {
        $deletableObjects = [];

        foreach ($files as $filepath) {
            $deletableObjects[] = [
                "Key" => empty($this->prefix) ? $filepath : rtrim($this->prefix, '/') . '/' . ltrim($filepath, '/'),
            ];
        }

        $this->client->deleteObjects([
            "Bucket" => $this->bucket,
            "Delete" => [
                "Objects" => $deletableObjects,
                "Quiet"   => true,
            ],
        ]);

        return true;
    }
}
