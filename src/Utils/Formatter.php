<?php

namespace Servdebt\SlimCore\Utils;
use \DateTime;
use \NumberFormatter;
use \Locale;


class Formatter
{

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
     * @param float $value
     * @param int $decimals
     * @param string $currencyCode
     * @return string
     */
    public static function currency(?float $value, int $decimals = 2, string $currencyCode = 'EUR' ) :string
    {
        $nf = new NumberFormatter(Locale::getDefault(), NumberFormatter::CURRENCY);
        $nf->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        if ($decimals == 0) {
            return preg_replace('/[,\.]00$/', '', $nf->formatCurrency(round((float)$value), $currencyCode));
        }
        return $nf->formatCurrency((float)$value, $currencyCode);
    }

    /**
     * returns a string with decimal formatted accordingly to locale settings
     * @param float $value
     * @param int $decimals
     * @return string
     */
    public static function decimal(?float $value, int $decimals = 2): string
    {
        $nf = new NumberFormatter(Locale::getDefault(), NumberFormatter::DECIMAL);
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
        for($i = 0; ($size / 1024) > 0.9; $i++, $size /= 1024) {}
        return round($size, $decimals).['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
    }


    /**
     * returns human readable number
     * @param float $value
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
        while($value > 1000){
            $value /= 1000;
            $index++;
            if ($index == count($readable)-1 || $readable[$index] == $maxIndex) break;
        }

        if ($index == 1) return self::decimal($value * 1000); // avoid formatting "m"

        return self::decimal($value, $decimals)." ".$readable[$index] . $suffix;
    }

	/**
     * returns a string with date/time formatted accordingly to params or empty string if date is invalid
	 * @param mixed $datetime format Y-m-d H:i:s
	 * @param string $dateWidth width of the date pattern. It can be 'FULL', 'LONG', 'MEDIUM' and 'SHORT'.
	 * If null, it means the date portion will NOT appear in the formatting result
	 * @param string $timeWidth width of the time pattern. It can be 'FULL', 'LONG', 'MEDIUM' and 'SHORT'.
	 * If null, it means the time portion will NOT appear in the formatting result
	 * @return string
     */
	public static function dateTime($datetime, $dateWidth = 'MEDIUM', $timeWidth = null)
	{
		$outputStr = "";
		$dateWidth = strtoupper($dateWidth);
		$timeWidth = strtoupper($timeWidth);
		try {
			if( empty($datetime) ) throw new Exception("datetime param cannot be empty");
			$outputFormat = "";

			if( $dateWidth !== null ){
				if( $dateWidth === 'FULL' )			$outputFormat = 'Y-m-d';
				elseif( $dateWidth === 'LONG' )		$outputFormat = 'Y-m-d';
				elseif( $dateWidth === 'MEDIUM' )	$outputFormat = 'Y-m-d';
				elseif( $dateWidth === 'SHORT' )	$outputFormat = 'Y-m-d';
			}
			if( $timeWidth !== null ){
				if(!empty($outputFormat))			$outputFormat .= ' ';

				if( $timeWidth === 'FULL' )			$outputFormat .= 'H:i:s';
				elseif( $timeWidth === 'LONG' )		$outputFormat .= 'H:i:s';
				elseif( $timeWidth === 'MEDIUM' )	$outputFormat .= 'H:i:s';
				elseif( $timeWidth === 'SHORT' )	$outputFormat .= 'H:i';
			}

			$dateObj = new DateTime($datetime);
			$outputStr = $dateObj->format($outputFormat);
		}
		catch (Exception $e) { $outputStr = ''; }
		return $outputStr;
	}
}
