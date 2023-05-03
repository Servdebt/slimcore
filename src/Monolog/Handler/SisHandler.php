<?php
namespace Servdebt\SlimCore\Monolog\Handler;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class SisHandler extends AbstractProcessingHandler
{
    protected $host;
    protected $apiKey;
    protected $token;
    protected $channel;
    protected $dateFormat;

    /**
     * @param string $host
     * @param string $appKey
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(string $host, string $appKey, int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->host     = $host;
        $this->appKey   = $appKey;
    }


    public function write(LogRecord $record): void
    {
        $record = $record->toArray();

        $url = $this->host.'?appKey='. $this->appKey;

        $headers = ['Content-Type: application/json'];
        
        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_POST, true);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($record));
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        try {
            $result = \Monolog\Handler\Curl\Util::execute($ch, 1, false);
        } catch (\Exception $e) {}
    }

}