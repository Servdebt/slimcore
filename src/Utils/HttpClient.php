<?php
namespace Servdebt\SlimCore\Utils;

use WpOrg\Requests\Requests;

class HttpClient
{

    const GET     = 'GET';
    const PUT     = 'PUT';
    const POST    = 'POST';
    const PATCH   = 'PATCH';
    const DELETE  = 'DELETE';
    const HEAD    = 'HEAD';
    const OPTIONS = 'OPTIONS';


    public static function request(string $method, string $url, object|array $urlParams = [],
                                   array $headers = [], array $options = [], mixed $data = []): object
    {
        if (!empty($urlParams)) {
            $url .= '?'.http_build_query($urlParams);
        }

        if (empty($options)) {
            $options = ['timeout' => 10, "connect_timeout"  => 10, 'verify' => false];
        }

        $response = match ($method) {
            self::GET => Requests::get($url, $headers, $options),
            self::PUT => Requests::put($url, $headers, $data, $options),
            self::POST => Requests::post($url, $headers, $data, $options),
            self::PATCH => Requests::patch($url, $headers, $data, $options),
            self::DELETE => Requests::delete($url, $headers, $options),
            self::HEAD => Requests::head($url, $headers, $options),
            self::OPTIONS => Requests::options($url, $headers, $data, $options),
        };

        return (object)[
            'code' => $response !== null ? $response->status_code : 404,
            'headers' => $response !== null ? $response->headers : (object)[],
            'body' => $response !== null ? $response->body : "",
            'isRedirect' => $response !== null && $response->is_redirect(),
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