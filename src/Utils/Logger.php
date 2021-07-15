<?php
namespace Servdebt\SlimCore\Utils;

class Logger
{

    public $startTime;
    private $entries = [];

    /**
     * Private constructor so nobody else can instantiate it
     */
    public function __construct()
    {
        $this->startTime = microtime(true);
    }


    /**
     * add a message to log
     *
     * @param mixed $message
     * @return Logger
     */
    public function add($message): Logger
    {
        if (is_object($message) || is_array($message)) {
            $message = json_encode($message);
        }

        $this->entries[] = (object)[
            'time' => round(microtime(true) - $this->startTime, 2),
            'message' => $message
        ];

        return $this;
    }


    /**
     * get logs as array
     *
     * @return array
     */
    public function get(): array
    {
        return $this->entries;
    }


    /**
     * get logs as a string
     *
     * @param bool $displayTime
     * @param string $lineBreak
     * @return string
     */
    public function getAsString(bool $displayTime = true, string $lineBreak = PHP_EOL): string
    {
        $str = "";
        foreach ($this->entries as $entry) {
            if ($displayTime) {
                $str .= "{$entry->time}s => {$entry->message}".$lineBreak;
            } else {
                $str .= $entry->message .$lineBreak;
            }
        }

        return $str;
    }


    /**
     * clear logs
     */
    public function clear()
    {
        $this->startTime = microtime(true);
        $this->entries = [];
    }

}