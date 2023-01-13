<?php

namespace Servdebt\SlimCore\Utils;
use Cassandra\Date;
use \DateTime;
use \NumberFormatter;
use \Locale;
use \IntlDateFormatter;


class Formatter
{

    public const FULL = 0;
    public const LONG = 1;
    public const MEDIUM = 2;
    public const SHORT = 3;
    public const NONE = -1;


    /**
     * @param DateTime|string $datetime
     * @param int $dateType
     * @param int $timeType
     * @param string|null $locale
     * @param mixed|null $timezone
     * @return string
     * @throws \Exception
     */
    public static function dateTime(DateTime|string $datetime = "now", int $dateType = 2, int $timeType = -1, ?string $locale = null, mixed $timezone = null): string
    {
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }

        return (new IntlDateFormatter(
            $locale ?? Locale::getDefault(),
            $dateType,
            $timeType,
            $timezone ?? date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
        ))->format($datetime);
    }


    /**
     * @param DateTime|string $datetime
     * @param string $format
     * @param mixed|null $timezone
     * @return string
     * @throws \Exception
     */
    public static function formatDate(DateTime|string $datetime = "now", string $format = 'Y-m-d H:i:s', mixed $timezone = null): string
    {
        $timezone = $timezone instanceof \DateTimeZone ? $timezone :
            new \DateTimeZone($timezone ?? date_default_timezone_get());

        if (is_string($datetime)) {
            $datetime = new DateTime($datetime, $timezone);
        } elseif ($datetime instanceof DateTime) {
            $datetime->setTimezone($timezone);
        }

        return $datetime->format($format);
    }


    /**
     * @param DateTime $datetime
     * @param bool $full
     * @return string
     * @throws \Exception
     */
    public static function dateToTimeElapsed(DateTime $datetime, bool $full = false): string
    {
        $now = new DateTime;
        $ago = $datetime;
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);

        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }


    /**
     * returns a string with currency formatted accordingly to locale settings
     * @param ?float $value
     * @param int $decimals
     * @param string $currencyCode
     * @param string|null $locale
     * @return string
     */
    public static function currency(?float $value, int $decimals = 2, string $currencyCode = 'EUR', ?string $locale = null) :string
    {
        $nf = new NumberFormatter($locale ?? Locale::getDefault(), NumberFormatter::CURRENCY);
        $nf->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        if ($decimals == 0) {
            return preg_replace('/[,\.]00$/', '', $nf->formatCurrency(round((float)$value), $currencyCode));
        }

        return $nf->formatCurrency((float)$value, $currencyCode);
    }


    /**
     * returns a string with decimal formatted accordingly to locale settings
     * @param ?float $value
     * @param string|null $locale
     */
    public static function int(?float $value, ?string $locale = null): string
    {
        return self::decimal($value, 0, $locale);
    }


    /**
     * returns a string with decimal formatted accordingly to locale settings
     * @param ?float $value
     * @param int $decimals
     * @param string|null $locale
     */
    public static function decimal(?float $value, int $decimals = 2, ?string $locale = null): string
    {
        $nf = new NumberFormatter($locale ?? Locale::getDefault(), NumberFormatter::DECIMAL);
        $nf->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $nf->format((float)$value);
    }


    /**
     * returns human readable file size
     * @param int $size
     * @param int $decimals
     * @return string
     */
    public static function readableFilesize(int $size, int $decimals = 2): string
    {
        for ($i = 0; ($size / 1024) > 0.9; $i++, $size /= 1024) {}

        return round($size, $decimals).['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
    }


    /**
     * returns human readable number
     * @param ?float $value
     * @param int $decimals
     * @param string $maxIndex
     * @param string $suffix
     * @return string
     */
    public static function readableNumber(?float $value, int $decimals = 2, string $maxIndex = 'B', string $suffix = ''): string
    {
        $value = (float)$value;
        $readable = array("", "k", "M", "B");
        $index = 0;
        while ($value > 1000) {
            $value /= 1000;
            $index++;
            if ($index == count($readable)-1 || $readable[$index] == $maxIndex) break;
        }

        if ($index == 1) return self::decimal($value * 1000); // avoid formatting "m"

        return self::decimal($value, $decimals)." ".$readable[$index] . $suffix;
    }

}