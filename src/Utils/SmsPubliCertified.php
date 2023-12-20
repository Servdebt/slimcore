<?php

namespace Servdebt\SlimCore\Utils;

class SmsPubliCertified
{
    public string $username;
    public string $password;
    public string $senderName;
    public string $reportUrl;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function setFrom(string $name): self
    {
        $this->senderName = $name;

        return $this;
    }

    public function setReportUrl(string $url): self
    {
        $this->reportUrl = $url;

        return $this;
    }

    public function sendSms(int|string $countryCode, int|string $destination, string $message, ?string $sendAt = null) : object
    {
        /*
        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $message, $urls);
        $link = '';
        if (count($urls[0]) == 1) {
            $link = $urls[0][0];
            $message = str_replace($link, '', $message);
        }
        */

        $res = HttpClient::request(HttpClient::GET, 'https://sms.avivavoice.com/AvivaSMS/httpapi', [
            'action' => 'submitMessage',
            'user' => $this->username,
            'password' => $this->password,
            'sender'    => $this->senderName,
            'recipients' => $countryCode.$destination,
            'text' => urlencode($message),
            'deliveryReportUrl' => $this->reportUrl,
            'deferredDelivery' => str_replace([" ", "-", ":"], "", $sendAt ?? ''),
            //'smsSite' => $link,
        ]);

        return (object)['code' => $res->code, 'body' => json_decode($res->body)];
    }

}