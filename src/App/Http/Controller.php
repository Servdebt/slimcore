<?php

namespace Jupitern\Slim3\App\Http;

class Controller
{
    /** @var \Psr\Http\Message\ServerRequestInterface */
    public $request;
    /** @var \Psr\Http\Message\ResponseInterface */
    public $response;


    public function __construct()
    {
        $this->request  = app()->request;
        $this->response = app()->response;
    }


    public function getQueryParam($paramName, $defaultValue = null)
    {
        $params = $this->request->getQueryParams();

        return isset($params[$paramName]) ? $params[$paramName] : $defaultValue;
    }

}