<?php

namespace Servdebt\SlimCore\Utils;
use Psr\Log\LogLevel;

class NosRESTGateway
{ 
    public $params;
    
    public $response;
    public $error;
    
    public function __construct($params = null) 
    {
        $this->params = $params;
    }
    
    public function SendSMSWithDLR($countryCode, $destination, $message) :bool
    {
        $resource = 'sendsmswithdlr';
        $url = $this->buildUrl($resource);
        $this->error = "";

        if (!empty($countryCode) && $countryCode != '351') {
            $destination = str_replace(' ', '', '00' . trim($countryCode)) . str_replace(' ', '', trim($destination));
        }
        
        $suffixUrl = "&from={$this->params['source']}&to={$destination}&text=" . urlencode($message);
        $url .= $suffixUrl;
        /**
         * @return String|Boolean XML String on success, false on error
         */
        $arrContextOptions = [
            "ssl" => [
                "verify_peer"      => false,
                "verify_peer_name" => false,
            ],
        ];

        $response = @file_get_contents($url, false, stream_context_create($arrContextOptions));

        if ($response === false) {
            addLog(LogLevel::ERROR, "SMS send failed", ["url" => $url]);
            return false;
        }

        $xmlObject = simplexml_load_string($response);

        if ($xmlObject->ReturnCode != 0) {
            $this->error = "SMS send failed. ". $xmlObject->ReturnMessage;
            return false;
        }

        return true;
    }


    private function buildUrl($resource)
    {
        // this is the base url for all REST Requests.
        // https://smspro.nos.pt/smspro/:tenant/:resource.aspx?username=:username&password=:password
        $search = array(':tenant', ':resource', ':user', ':password');
        $values = array($this->params['tenant'], $resource, $this->params['user'], $this->params['password']);
        
        return str_replace($search, $values, $this->params['host']);
    }

}
