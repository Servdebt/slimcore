<?php

namespace Servdebt\SlimCore\Utils;
use \DateTime;
use \NumberFormatter;
use \Locale;
use \IntlDateFormatter;

class Formatter
{

    public static function dateTime(DateTime|string|null $datetime = 'now', bool $showDate = true, bool $showTime = false, ?string $locale = null, mixed $timezone = null): string
    {
        if (empty($datetime)) {
            return "";
        }
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }

        return (new IntlDateFormatter(
            $locale ?? Locale::getDefault(),
            $showDate ? IntlDateFormatter::MEDIUM : IntlDateFormatter::NONE,
            $showTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
            $timezone ?? date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
        ))->format($datetime);
    }


    public static function dateFormat(DateTime|string|null $datetime = 'now', string $format = 'Y-m-d H:i', mixed $timezone = null): string
    {
        if (empty($datetime)) {
            return "";
        }

        $timezone = $timezone instanceof \DateTimeZone ? $timezone :
            new \DateTimeZone($timezone ?? date_default_timezone_get());

        if (is_string($datetime)) {
            $datetime = new DateTime($datetime, $timezone);
        } elseif ($datetime instanceof DateTime) {
            $datetime->setTimezone($timezone);
        }

        return $datetime->format($format);
    }


    public static function dateToTimeElapsed(?DateTime $datetime = null, bool $full = false): string
    {
        if (empty($datetime)) {
            return "";
        }

        $now = new DateTime;
        $ago = $datetime;
        $diff = $now->diff($ago);

        $string = [
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];
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


    public static function currency(?float $value, int $decimals = 2, string $currencyCode = 'EUR', ?string $locale = null) :string
    {
        $nf = new NumberFormatter($locale ?? Locale::getDefault(), NumberFormatter::CURRENCY);
        $nf->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        if ($decimals == 0) {
            return preg_replace('/[,\.]00$/', '', $nf->formatCurrency(round((float)$value), $currencyCode));
        }

        return $nf->formatCurrency((float)$value, $currencyCode);
    }


    public static function int(?float $value, ?string $locale = null): string
    {
        return self::decimal($value, 0, $locale);
    }


    public static function decimal(?float $value, int $decimals = 2, ?string $locale = null): string
    {
        $nf = new NumberFormatter($locale ?? Locale::getDefault(), NumberFormatter::DECIMAL);
        $nf->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $nf->format((float)$value);
    }


    public static function percentage($value, int $decimals = 0)
    {
        $nf = new NumberFormatter(Locale::getDefault(), NumberFormatter::PERCENT);
        $nf->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $nf->format($value);
    }


    public static function readableFilesize(int $size, int $decimals = 2): string
    {
        for ($i = 0; ($size / 1024) > 0.9; $i++, $size /= 1024) {}

        return round($size, $decimals).['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
    }


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


    public static function iban($value, $separator=' '): string
    {
        return chunk_split($value ?? "", 4, $separator);
    }

}