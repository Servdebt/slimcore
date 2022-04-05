<?php
namespace Servdebt\SlimCore\Utils;

use WpOrg\Requests\Requests;

class HttpClient
{

    const GET = 'GET';
    const HEAD = 'HEAD';
    const POST = 'POST';
    const PUT = 'PUT';
    const DELETE = 'DELETE';
    const OPTIONS = 'OPTIONS';
    const PATCH = 'PATCH';


    public static function request($method, $url, $urlParams = [], $headers = [], $options = [], $data = [])
    {
        if (!empty($urlParams)) {
            $url .= '?'.http_build_query($urlParams);
        }

        if (empty($options)) {
            $options = ['timeout' => 10, "connect_timeout"  => 10, 'verify' => false];
        }

        try {
            $response = null;
            switch ($method) {
                case self::GET:
                    $response = Requests::get($url, $headers, $options);
                    break;
                case self::HEAD:
                    $response = Requests::head($url, $headers, $options);
                    break;
                case self::POST:
                    $response = Requests::post($url, $headers, $data, $options);
                    break;
                case self::PUT:
                    $response = Requests::put($url, $headers, $data, $options);
                    break;
                case self::DELETE:
                    $response = Requests::delete($url, $headers, $options);
                    break;
                case self::OPTIONS:
                    $response = Requests::options($url, $headers, $data, $options);
                    break;
                case self::PATCH:
                    $response = Requests::patch($url, $headers, $data, $options);
                    break;
            }
        } catch (Requests_Exception $e) {
            $response = null;
        }

        return (object)[
            'code' => $response !== null ? $response->status_code : 404,
            'headers' => $response !== null ? $response->headers : (object)[],
            'body' => $response !== null ? $response->body : "",
            'isRedirect' => $response !== null ? $response->is_redirect() : false,
        ];
    }


    public static function desktopVersion(string $url): string
    {
        // Mobile version of the link => desktop equivalent
        $mobileVersions = [
            "://mobile.twitter" => "://twitter",
        ];

        foreach ($mobileVersions as $mobile => $desktop) {
            $url = str_replace($mobile, $desktop, $url);
        }

        return $url;
    }


    public static function removeTrackingQueryParams(string $url): string
    {
        $trackers = [
            "utm_source",
            "utm_medium",
            "utm_term",
            "utm_campaign",
            "utm_content",
            "utm_name",
            "utm_cid",
            "utm_reader",
            "utm_viz_id",
            "utm_pubreferrer",
            "utm_swu",
            "gclid",
            "icid",
            "fbclid",
            "_hsenc",
            "_hsmi",
            "mkt_tok",
            "mc_cid",
            "mc_eid",
            "sr_share",
            "vero_conv",
            "vero_id",
            "nr_email_referer",
            "ncid",
            "ref",
            "gclsrc",
            "_ga",
            "s_kwcid",
            "msclkid",
        ];

        foreach ($trackers as $key) {
            $url = preg_replace('/(?:&|(\?))' . $key . '=[^&]*(?(1)&|)?/i', "$1", $url);
            $url = rtrim($url, "?");
            $url = rtrim($url, "&");
        }

        return rtrim($url, "/");
    }

}