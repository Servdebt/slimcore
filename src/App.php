<?php

namespace Servdebt\SlimCore;

use Servdebt\SlimCore\Handlers\NotAllowed;
use Servdebt\SlimCore\Handlers\NotFound;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Servdebt\SlimCore\Handlers\Error;
use Servdebt\SlimCore\Utils\DotNotation;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Handlers\ErrorHandler;

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

        AppFactory::setContainer(new \DI\Container());
        $this->slim = AppFactory::create();
        $this->registerInContainer('request', (ServerRequestCreatorFactory::create())->createServerRequestFromGlobals());
        $this->registerInContainer('response', $this->slim->getResponseFactory()->createResponse());

        date_default_timezone_set($this->configs['timezone']);
        \Locale::setDefault($this->configs['locale']);

        $this->bootstrap();
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

    public function bootstrap()
    {
        $this->addRoutingMiddleware();
        $this->registerProviders();
//            $this->registerMiddleware();
        $this->registerErrorHandlers();
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

    public function registerInContainer(string $name, $value): void
    {
        ($this->slim->getContainer())->set($name, $value);
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
     * @param mixed $param
     * @param mixed $defaultValue
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
    public function registerProviders(): void
    {
        $services = (array)$this->getConfig('services');
        foreach ($services as $serviceName => $service) {
            if (!isset($service['on']) || strpos($service['on'], $this->appName) !== false) {
                $service['provider']::register($this, $serviceName, $service['settings'] ?? []);
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

    public function registerErrorHandlers()
    {
        $errorMiddleware = $this->addErrorMiddleware($this->configs['debug'] ?? false, $logErrors = true, $logErrorDetails = false, $this->resolve('logger'));

        $errorMiddleware->setErrorHandler(
            HttpNotFoundException::class,
            new NotFound(
                $this->getCallableResolver(),
                $this->getResponseFactory(),
                $this->resolve('logger')
            )
        );

        $errorMiddleware->setErrorHandler(
            HttpMethodNotAllowedException::class,
            new NotAllowed(
                $this->getCallableResolver(),
                $this->getResponseFactory(),
                $this->resolve('logger')
            )
        );

        $errorMiddleware->setDefaultErrorHandler(
            new Error(
                $this->getCallableResolver(),
                $this->getResponseFactory(),
                $this->resolve('logger')
            )
        );
    }


    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return $this->getContainer()->has($name);
    }


    /**
     * magic method to set a property of the app or insert something in the container
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->slim->{$name} = $value;
        } else {
            $this->registerInContainer($name, $value);
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
        if (property_exists($this, $name)) {
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
        throw new \Exception('Method not found :: ' . $fn);
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

        return strtolower($baseUrl . $indexFile . $url);
    }


    /**
     * return a response object
     *
     * @param mixed $resp
     *
     * @throws \ReflectionException
     */
    public function sendResponse($resp)
    {
        if ($resp instanceof Response) {
            return $resp;
        }

        $response = $this->resolve('response');

        if (is_array($resp) || is_object($resp)) {
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($resp));
            return $response;
        }

        $response->getBody()->write($resp);

        return $response;
    }


    /**
     * resolve and call a given class / method
     *
     * @param callable|array $classMethod [ClassNamespace, method]
     * @param array $requestParams params from url
     * @param bool $useReflection
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
     * @param string $name
     * @param array $params
     * @param mixed
     * @return mixed
     * @throws \ReflectionException
     */
    public function resolve($name, $params = [])
    {
        $c = $this->getContainer();

        if ($c->has($name)) {
            return is_callable($c->get($name)) ? call_user_func_array($c->get($name), $params) : $c->get($name);
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
        return array_map(function($dependency) use ($urlParams) {
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

        if (!$param->getClass()) {
            throw new \ReflectionException("Unable to resolve method param {$param->name}");
        }

        // try to resolve from container
        return $this->resolve($param->getClass()->name);
    }


    public function notFound(): void
    {
        throw new HttpNotFoundException($this->request);
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
     *
     * @throws \ReflectionException
     */
    function error($code = 500, $error = '', $messages = [])
    {
        if ($this->resolve('request')->getHeaderLine('Accept') == 'application/json') {
            $response = $this->resolve('response')
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($code);
            $response->getBody()->write(json_encode(['code' => $code, 'error' => $error, 'messages' => $messages]));
            return $response;
        }

        $resp = $this->resolve('view')->render('http::error', [
            'code'     => $code,
            'error'    => $error,
            'messages' => $messages,
        ]);

        $response = $this->resolve('response')->withStatus($code);
        $response->getBody()->write($resp);

        return $response;
    }


    function consoleError($error, $messages = [])
    {
        $response = $this->resolve('response')->withHeader('Content-type', 'text/plain');
        $response->getBody()->write($error . PHP_EOL . implode(PHP_EOL, $messages));

        return $response;
    }

}
