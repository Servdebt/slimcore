<?php
namespace Servdebt\SlimCore\Monolog\Handler;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Telegram Handler For Monolog
 *
 * This class helps you in logging your application events
 * into telegram using it's API.
 *
 * @author Moein Rahimi <m.rahimi2150@gmail.com>
 */

class TelegramHandler extends AbstractProcessingHandler
{
    protected $timeOut;
    protected $token;
    protected $channel;
    protected $dateFormat;

    /**
     * @var array
     */
    protected $curlOptions;

    const host = 'https://api.telegram.org/bot';

    /**
     * getting token a channel name from Telegram Handler Object.
     *
     * @param string $token Telegram Bot Access Token Provided by BotFather
     * @param string $channel Telegram Channel userName
     * @param int $level
     * @param bool $bubble
     */
    public function __construct($token, $channel, $level = \Monolog\Logger::DEBUG, $bubble = true) 
    {
        parent::__construct($level, $bubble);

        $this->token        = $token;
        $this->channel      = $channel;
        $this->dateFormat   = 'Y-m-d H:i:s';
        $this->timeOut      = 100;
        $this->curlOptions  = [];
    }


    /**
     * @param array $record
     * @return void
     */
    public function write(LogRecord $record): void
    {
        $record = $record->toArray();

        $appName = array_key_exists('appName', $record['context']) ? $record['context']['appName'] : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $message = $this->getEmoji($record['level']) .' '. $appName .' - '.$record['level_name'] .PHP_EOL .$record['message'];
        
        $ch = \curl_init();
        $url = self::host . $this->token . "/SendMessage";
        $timeOut = $this->timeOut;

        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        \curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
            'text'    => $message,
            'chat_id' => $this->channel,
        )));

        foreach ($this->curlOptions as $option => $value) {
            \curl_setopt($ch, $option, $value);
        }

        try {
            $result = \Monolog\Handler\Curl\Util::execute($ch, 1, false);
            //debug($result, true);
        } catch (\Exception $e) {}
    }


    /**
     * make emoji for log events
     * @return array
     *
     */
    protected function emojiMap()
    {
        return [
            Logger::DEBUG     => '',
            Logger::INFO      => '‍',
            Logger::NOTICE    => '',
            Logger::WARNING   => '⚡️',
            Logger::ERROR     => '⚠',
            Logger::CRITICAL  => '⚠',
            Logger::ALERT     => '⚠',
            Logger::EMERGENCY => '🚨',
        ];
    }


    /**
     * return emoji for given level
     *
     * @param $level
     * @return string
     */
    protected function getEmoji($level)
    {
        $levelEmojiMap = $this->emojiMap();
        return $levelEmojiMap[$level];
    }

}