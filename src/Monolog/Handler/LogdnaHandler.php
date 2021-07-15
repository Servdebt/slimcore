<?php

/*
 * This file is part of the Zwijn/Monolog package.
 *
 * (c) Nicolas Vanheuverzwijn <nicolas.vanheu@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Servdebt\SlimCore\Monolog\Handler;
use Monolog\Formatter\FormatterInterface;

/**
 * Sends log to Logdna. This handler uses logdna's ingestion api.
 *
 * @see https://docs.logdna.com/docs/api
 * @author Nicolas Vanheuverzwijn
 */
class LogdnaHandler extends \Monolog\Handler\AbstractProcessingHandler {

    /**
     * @var string $ingestion_key
     */
    private $ingestion_key;

    /**
     * @var string $hostname
     */
    private $hostname;

    /**
     * @var string $ip
     */
    private $ip = '';

    /**
     * @var string $mac
     */
    private $mac = '';

    /**
     * @param string $value
     */
    public function setIP($value) 
    {
        $this->ip = $value;
    }

    /**
     * @param string $value
     */
    public function setMAC($value) 
    {
        $this->mac = $value;
    }

    /**
     * @param string $ingestion_key
     * @param string $hostname
     * @param int $level
     * @param bool $bubble
     */
    public function __construct($ingestion_key, $hostname, $level = \Monolog\Logger::DEBUG, $bubble = true) 
    {
        parent::__construct($level, $bubble);

        if (!\extension_loaded('curl')) {
            throw new \LogicException('The curl extension is needed to use the LogdnaHandler');
        }

        $this->ingestion_key = $ingestion_key;
    }

    /**
     * @param array $record
     * @return void
     */
    protected function write(array $record): void 
    {
        $headers = ['Content-Type: application/json'];
        $data = $record["formatted"];
        $appName = urlencode(array_key_exists('appName', $record['context']) ? $record['context']['appName'] : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $url = \sprintf("https://logs.logdna.com/logs/ingest?hostname=%s&mac=%s&ip=%s&now=%s", $appName, $this->mac, $this->ip, $record['datetime']->getTimestamp());

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_USERPWD, "$this->ingestion_key:");
        \curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        \curl_setopt($ch, CURLOPT_POST, true);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        try {
            $result = \Monolog\Handler\Curl\Util::execute($ch, 1, false);
            //debug($result, true);
        } catch (\Exception $e) {}
    }

    /**
     * @return \Servdebt\SlimCore\Monolog\Formatter\LogdnaFormatter
     */
    protected function getDefaultFormatter(): FormatterInterface 
    {
        return new \Servdebt\SlimCore\Monolog\Formatter\LogdnaFormatter();
    }
}
