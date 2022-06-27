<?php

namespace Servdebt\SlimCore\App\Http;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Servdebt\SlimCore\Utils\DotNotation;

class Controller
{
    public Request $request;
    public Response $response;

    private DotNotation $postParams;


    public function __construct(Request $request, Response $response)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->postParams = new DotNotation((array)($request->getParsedBody() ?? []));
    }


    public function getQueryParam($paramName, $defaultValue = null)
    {
        $params = $this->request->getQueryParams();

        return $params[$paramName] ?? $defaultValue;
    }


    public function getPostParam($paramName, $defaultValue = null)
    {
        return $this->postParams->get($paramName, $defaultValue);
    }

}