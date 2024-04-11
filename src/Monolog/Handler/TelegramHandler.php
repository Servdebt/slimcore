<?php
namespace Servdebt\SlimCore\Monolog\Handler;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger;

class TelegramHandler extends AbstractProcessingHandler
{
    protected string $token;
    protected string $channel;
    protected string $dateFormat;

    /**
     * @var array
     */
    protected array $curlOptions;

    const host = 'https://api.telegram.org/bot';

    /**
     * getting token a channel name from Telegram Handler Object.
     *
     * @param string $token Telegram Bot Access Token Provided by BotFather
     * @param string $channel Telegram Channel userName
     * @param int|string|Level $level
     * @param bool $bubble
     */
    public function __construct(string $token, string $channel, int|string|Level $level = 100, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->token        = $token;
        $this->channel      = $channel;
        $this->dateFormat   = 'Y-m-d H:i:s';
        $this->curlOptions  = [];
    }


    public function write(array|LogRecord $record): void
    {
        if ($record instanceof LogRecord) {
            $record = $record->toArray();
        }

        $appName = array_key_exists('appName', $record['context']) ? $record['context']['appName'] : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $message = $this->getEmoji($record['level']) .' '. $appName .' - '.$record['level_name'] .PHP_EOL .$record['message'];
        
        $ch = \curl_init();
        $url = self::host . $this->token . "/SendMessage";

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
            \Monolog\Handler\Curl\Util::execute($ch, 1, false);
        } catch (\Exception $e) {}
    }


    protected function emojiMap(): array
    {
        return [
            100 => '',
            200 => '‍',
            250 => '',
            300 => '⚡️',
            400 => '⚠',
            500 => '⚠',
            550 => '⚠',
            600 => '🚨',
        ];
    }


    protected function getEmoji(int $level): string
    {
        $levelEmojiMap = $this->emojiMap();
        return $levelEmojiMap[$level];
    }

}