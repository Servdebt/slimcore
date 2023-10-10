<?php

namespace Servdebt\SlimCore\Utils;

class SmsPubli
{
    public string $apiKey;
    public string $username;
    public string $senderName;
    public string $reportUrl;

    public function __construct($apiKey, $username = null)
    {
        $this->apiKey = $apiKey;
        $this->username = $username;
    }

    public function setFrom($name): self
    {
        $this->senderName = $name;

        return $this;
    }

    public function setReportUrl($url): self
    {
        $this->reportUrl = $url;

        return $this;
    }

    public function sendSms($countryCode, $destination, $message, $sendAt = null) :mixed
    {
        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $message, $urls);
        $link = '';
        if (count($urls[0]) == 1) {
            $link = $urls[0][0];
            $message = str_replace($link, '{LINK}', $message);
        }

        $data = json_encode([
            "api_key" => $this->apiKey,
            "user_name" => $this->username,
            "report_url" => $this->reportUrl,
            "concat" => 1,
            "dlr_description" => 1,
            "link" => $link,
            "messages" => [
                [
                    "from"    => $this->senderName,
                    "to"      => $countryCode.$destination,
                    "text"    => $message,
                    "send_at" => $sendAt ?? date("Y-m-d H:i:s"),
                ]
            ]
        ]);

        $res = HttpClient::request(HttpClient::POST,
            'https://api.gateway360.com/api/3.0/sms/send' . (!empty($link) ? '-link' : ''),
            [],
            ['Content-Type' => 'application/json'],
            [],
            $data
        );

        return (object)['code' => $res->code, 'body' => json_decode($res->body)];
    }

}