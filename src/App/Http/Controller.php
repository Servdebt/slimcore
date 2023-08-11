<?php

namespace Servdebt\SlimCore\App\Http;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Servdebt\SlimCore\Utils\DotNotation;
use Servdebt\SlimCore\Utils\Session;

class Controller
{
    public Request $request;
    public Response $response;

    public DotNotation $postParams;


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


    public function isAjax()
    {
        return strtolower($this->request->getHeaderLine('X-Requested-With')) == strtolower('XMLHttpRequest');
    }


    public function isPost()
    {
        return strtolower($this->request->getMethod()) == 'post';
    }


    public function getPostParam($paramName, $defaultValue = null): mixed
    {
        return $this->postParams->get($paramName, $defaultValue);
    }


    public function getPostParams(): array
    {
        return $this->postParams->getValues();
    }


    public function setFlashMessage($key, $value): void
    {
        Session::set("flash-message-$key", json_encode($value));
    }


    public function getFlashMessage($key, $defaultValue): mixed
    {
        $val = Session::get("flash-message-$key");
        Session::delete("flash-message-$key");

        return $val ? json_decode($val) : $defaultValue;
    }

}