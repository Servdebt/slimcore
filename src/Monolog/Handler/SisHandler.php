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
    public function __construct($host, $appKey, $level = \Monolog\Logger::DEBUG, $bubble = true
    ) 
    {
        parent::__construct($level, $bubble);

        $this->host     = $host;
        $this->appKey   = $appKey;
    }


    /**
     * @param array $record
     * @return void
     */
    public function write(array $record): void
    {
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
            //debug($result, true);
        } catch (\Exception $e) {}
    }

}