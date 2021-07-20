<?php

namespace Servdebt\SlimCore\App\Http;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Controller
{
    /** @var \Psr\Http\Message\ServerRequestInterface */
    public $request;
    /** @var \Psr\Http\Message\ResponseInterface */
    public $response;


    public function __construct(Request $request, Response $response)
    {
        $this->request  = $request;
        $this->response = $response;
    }


    public function getQueryParam($paramName, $defaultValue = null)
    {
        $params = $this->request->getQueryParams();

        return isset($params[$paramName]) ? $params[$paramName] : $defaultValue;
    }

}