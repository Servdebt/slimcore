<?php

namespace Jupitern\Slim3;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Jupitern\Slim3\Handlers\Error;
use Jupitern\Slim3\Handlers\PhpError;
use Jupitern\Slim3\Handlers\NotFound;
use Jupitern\Slim3\Handlers\NotAllowed;
use Jupitern\Slim3\Utils\DotNotation;

class App
{

    public $appName;

    const DEVELOPMENT = 'development';
    const STAGING = 'staging';
    const PRODUCTION = 'production';
    public $env = self::DEVELOPMENT;

    /** @var \Slim\App */
    private $slim = null;
    private $configs = [];
    private static $instance = null;


    /**
     * @param string $appName
     * @param array $configs
     */
    protected function __construct($appName = '', $configs = [])
    {
        $this->appName = $appName;
        $this->configs = $configs;

        $this->slim = new \Slim\App($this->configs['slim']);
        $this->env = $this->configs['env'];
        $container = $this->getContainer();
        $displayErrorDetails = $this->configs['debug'];

        date_default_timezone_set($this->configs['timezone']);
        \Locale::setDefault($this->configs['locale']);

        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if (!($errno & error_reporting())) {
                return;
            }
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error !== null) {
                $throwable = new \ErrorException($error["message"], $error["type"], 0, $error["file"], $error["line"]);
                $handler = new PhpError();
                $handler(app()->resolve("request"), app()->resolve("response"), $throwable);
            }
        });

        $container['errorHandler'] = function() use($displayErrorDetails) {
            return new Error($displayErrorDetails);
        };
        $container['phpErrorHandler'] = function() use($displayErrorDetails) {
            return new PhpError($displayErrorDetails);
        };
        $container['notAllowedHandler'] = function() use($displayErrorDetails) {
            return new NotAllowed();
        };
        $container['notFoundHandler'] = function() {
            return new NotFound();
        };
    }

    /**
     * Application Singleton Factory
     *
     * @param string $appName
     * @param array $configs
     * @return static
     */
    final public static function instance($appName = '', $configs = [])
    {
        if (null === static::$instance) {
            static::$instance = new static($appName, $configs);
        }

        return static::$instance;
    }


    /**
     * get if running application is console
     *
     * @return boolean
     */
    public function isConsole()
    {
        return php_sapi_name() == 'cli';
    }


    /**
     * set configuration param
     *
     * @return \Psr\Container\ContainerInterface
     */
    public function getContainer()
    {
        return $this->slim->getContainer();
    }


    /**
     * set configuration param
     *
     * @param string $param
     * @param mixed $value
     */
    public function setConfig($param, $value)
    {
        $dn = new DotNotation($this->configs);
        $dn->set($param, $value);
    }


    /**
     * get configuration param
     *
     * @param string $param
     * @param string $defaultValue
     * @return mixed
     */
    public function getConfig($param, $defaultValue = null)
    {
        $dn = new DotNotation($this->configs);
        return $dn->get($param, $defaultValue);
    }


    /**
     * register providers
     *
     * @return void
     */
    public function registerProviders()
    {
        $services = (array)$this->getConfig('services');

        foreach ($services as $serviceName => $service) {
            if (!isset($service['on']) || strpos($service['on'], $this->appName) !== false) {
                $service['provider']::register($serviceName, $service['settings'] ?? []);
            }
        }
    }


    /**
     * register providers
     *
     * @return void
     */
    public function registerMiddleware()
    {
        $middlewares = array_reverse((array)$this->getConfig('middleware'));
        array_walk($middlewares, function($appName, $middleware) {
            if (strpos($appName, $this->appName) !== false) {
                $this->slim->add(new $middleware);
            }
        });
    }


    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        $c = $this->getContainer();
        return $c->has($name);
    }


    /**
     * magic method to set a property of the app or insert something in the container
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (property_exists($this,$name)) {
            $this->slim->{$name} = $value;
        } else {
            $this->getContainer()[$name] = $value;
        }
    }


    /**
     * magic method to get a property of the App or resolve something from the container
     * @param $name
     * @return mixed
     * @throws \ReflectionException
     */
    public function __get($name)
    {
        if (property_exists($this,$name)) {
            return $this->slim->{$name};
        } else {
            $c = $this->getContainer();

            if ($c->has($name)) {
                return $c->get($name);
            }
        }

        return $this->resolve($name);
    }


    /**
     * @param $fn
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($fn, $args = [])
    {
        if (method_exists($this->slim, $fn)) {
            return call_user_func_array([$this->slim, $fn], $args);
        }
        throw new \Exception('Method not found :: '.$fn);
    }


    /**
     * generate a url
     *
     * @param string $url
     * @param boolean|null $showIndex pass null to assume config file value
     * @param boolean $includeBaseUrl
     * @return string
     */
    public function url($url = '', $showIndex = null, $includeBaseUrl = true)
    {
        $baseUrl = $includeBaseUrl ? $this->getConfig('baseUrl') : '';

        $indexFile = '';
        if ($showIndex || ($showIndex === null && (bool)$this->getConfig('indexFile'))) {
            $indexFile = 'index.php/';
        }
        if (strlen($url) > 0 && $url[0] == '/') {
            $url = ltrim($url, '/');
        }

        return strtolower($baseUrl.$indexFile.$url);
    }


    /**
     * return a response object
     *
     * @param mixed $resp
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \ReflectionException
     */
    public function sendResponse($resp)
    {
        if ($resp instanceof ResponseInterface) {
            return $resp;
        }

        $response = $this->resolve('response');
        if (is_array($resp) || is_object($resp)) {
            return $response->withJson($resp);
        }

        return $response->write($resp);
    }


    /**
     * resolve and call a given class / method
     *
     * @param callable|array $classMethod        [ClassNamespace, method]
     * @param array $requestParams      params from url
     * @param bool $useReflection
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \ReflectionException
     */
    public function resolveRoute($classMethod, $requestParams = [], $useReflection = true)
    {
        try {
            $className = $classMethod[0];
            $methodName = $classMethod[1];

            if (!$useReflection) {
                if (class_exists($className)) {
                    $controller = new $className;
                } else {
                    return $this->notFound();
                }
                $method = new \ReflectionMethod($controller, $methodName);
            } else {
                // adicional code to inject dependencies in controller class constructor
                $class = new \ReflectionClass($className);
                if (!$class->isInstantiable() || !$class->hasMethod($methodName)) {
                    throw new \ReflectionException("route class is not instantiable or method does not exist");
                }

                $constructorArgs = $this->resolveMethodDependencies($class->getConstructor());
                $controller = $class->newInstanceArgs($constructorArgs);

                $method = $class->getMethod($methodName);
            }

        } catch (\ReflectionException $e) {
            return $this->notFound();
        }

        $args = $this->resolveMethodDependencies($method, $requestParams);
        $ret = $method->invokeArgs($controller, $args);

        return $this->sendResponse($ret);
    }


    /**
     * resolve a dependency from the container
     *
     * @throws \ReflectionException
     * @param string $name
     * @param array $params
     * @param mixed
     * @return mixed
     */
    public function resolve($name, $params = [])
    {
        $c = $this->getContainer();
        if ($c->has($name)) {
            return is_callable($c[$name]) ? call_user_func_array($c[$name], $params) : $c[$name];
        }

        if (!class_exists($name)) {
            throw new \ReflectionException("Unable to resolve {$name}");
        }

        $reflector = new \ReflectionClass($name);

        if (!$reflector->isInstantiable()) {
            throw new \ReflectionException("Class {$name} is not instantiable");
        }

        if ($constructor = $reflector->getConstructor()) {
            $dependencies = $this->resolveMethodDependencies($constructor);
            return $reflector->newInstanceArgs($dependencies);
        }

        return new $name();
    }


    /**
     * resolve dependencies for a given class method
     *
     * @param \ReflectionMethod $method
     * @param array $urlParams
     * @return array
     */
    private function resolveMethodDependencies(\ReflectionMethod $method, $urlParams = [])
    {
        return array_map(function ($dependency) use($urlParams) {
            return $this->resolveDependency($dependency, $urlParams);
        }, $method->getParameters());
    }


    /**
     * resolve a dependency parameter
     *
     * @param \ReflectionParameter $param
     * @param array $urlParams
     * @return mixed
     *
     * @throws \ReflectionException
     */
    private function resolveDependency(\ReflectionParameter $param, $urlParams = [])
    {
        // for controller method para injection from $_GET
        if (count($urlParams) && array_key_exists($param->name, $urlParams)) {
            return $urlParams[$param->name];
        }

        // param is instantiable
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if (!$param->getType()) {
            throw new \ReflectionException("Unable to resolve method param {$param->name}");
        }

        // try to resolve from container
        return $this->resolve($param->getClass()->name);
    }


    /**
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function notFound()
    {
        $handler = $this->getContainer()['notFoundHandler'];

        return $handler($this->getContainer()['request'], $this->getContainer()['response']);
    }


    /**
     * @param int $httpCode
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function code($httpCode = 200)
    {
        return $this->resolve('response')->withStatus($httpCode);
    }


    /**
     * @param int $code
     * @param string $error
     * @param array $messages
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \ReflectionException
     */
    function error($code = 500, $error = '', $messages = [])
    {
        if ($this->resolve('request')->getHeaderLine('Accept') == 'application/json') {
            return $this->resolve('response')
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($code)
                ->withJson(['code' => $code, 'error' => $error, 'messages' => $messages]);
        }

        $resp = $this->resolve('view')->render('http::error', [
            'code' => $code,
            'error' => $error,
            'messages' => $messages
        ]);

        return $this->resolve('response')
            ->withStatus($code)
            ->write($resp);
    }


    function consoleError($error, $messages = [])
    {
        return $this->resolve('response')
            ->withHeader('Content-type', 'text/plain')
            ->write($error.PHP_EOL.implode(PHP_EOL, $messages));
    }

}
