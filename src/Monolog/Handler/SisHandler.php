<?php
namespace Servdebt\SlimCore\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class SisHandler extends AbstractProcessingHandler
{
    protected string $host;
    protected ?string $appKey;

    /**
     * @param string $appKey
     * @param string|null $host
     * @param int|string|Level $level
     * @param bool $bubble
     */
    public function __construct(string $appKey, ?string $host = null, int|string|Level $level = 100, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->host     = $host ?? 'https://devapps.servdebt.pt:8010/log';
        $this->appKey   = $appKey;
    }


    public function write(array|LogRecord $record): void
    {
        if ($record instanceof LogRecord) {
            $record = $record->toArray();
        }

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