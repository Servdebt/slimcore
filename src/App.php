<?php

namespace Servdebt\SlimCore;

use Servdebt\SlimCore\Handlers\NotAllowed;
use Servdebt\SlimCore\Handlers\NotFound;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Servdebt\SlimCore\Handlers\Error;
use Servdebt\SlimCore\Utils\DotNotation;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

class App
{
    public ?string $appName;

    const DEVELOPMENT = 'development';
    const STAGING = 'staging';
    const PRODUCTION = 'production';

    public string $env = self::DEVELOPMENT;

    /** @var \Slim\App */
    private $slim = null;

    private array $configs = [];

    private static $instance = null;


    /**
     * @param string|null $appName
     * @param array $configs
     */
    protected function __construct(?string $appName = '', array $configs = [])
    {
        $this->appName = $appName;
        $this->configs = $configs;

        $builder = new \DI\ContainerBuilder();
        $builder->addDefinitions(require __DIR__.'/Config/container.php');
        $builder->useAutowiring(true);
        $builder->useAnnotations(false);
        $container = $builder->build();

        AppFactory::setContainer($container);
        $this->slim = AppFactory::create();

        $this->registerInContainer(Request::class, (ServerRequestCreatorFactory::create())->createServerRequestFromGlobals());
        $this->registerInContainer(Response::class, $this->slim->getResponseFactory()->createResponse());

        date_default_timezone_set($this->configs['timezone']);
        \Locale::setDefault($this->configs['locale']);

        $this->bootstrap();
    }

    /**
     * Application Singleton Factory
     *
     * @param string|null $appName
     * @param array $configs
     * @return static
     */
    final public static function instance(?string $appName = '', array $configs = []): self
    {
        if (null === static::$instance) {
            static::$instance = new static($appName, $configs);
        }

        return static::$instance;
    }

    public function run()
    {
        $this->slim->run($this->resolve(Request::class));
    }

    public function bootstrap(): void
    {
        $this->addRoutingMiddleware();
        $this->registerProviders();
        $this->registerMiddleware();
        $this->registerErrorHandlers();
    }

    public function isConsole(): bool
    {
        return php_sapi_name() === 'cli';
    }

    public function getContainer(): \Psr\Container\ContainerInterface
    {
        return $this->slim->getContainer();
    }

    public function registerInContainer(string $name, $value): void
    {
        ($this->slim->getContainer())->set($name, $value);
    }

    public function setConfig($param, $value): void
    {
        $dn = new DotNotation($this->configs);
        $dn->set($param, $value);
    }

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
    public function registerMiddleware(): void
    {
        $middlewares = array_reverse((array)$this->getConfig('middleware'));
        array_walk($middlewares, function($appName, $middleware) {
            if (strpos($appName, $this->appName) !== false) {
                $this->slim->add(new $middleware);
            }
        });
    }

    public function registerErrorHandlers(): void
    {
        $logger = $this->has('logger') ? $this->resolve('logger') : null;

        $errorMiddleware = $this->addErrorMiddleware($this->configs['debug'] ?? false, $logErrors = true, $logErrorDetails = false, $logger);

        $errorMiddleware->setErrorHandler(
            HttpNotFoundException::class,
            new NotFound(
                $this->getCallableResolver(),
                $this->getResponseFactory(),
                $logger
            )
        );

        $errorMiddleware->setErrorHandler(
            HttpMethodNotAllowedException::class,
            new NotAllowed(
                $this->getCallableResolver(),
                $this->getResponseFactory(),
                $logger
            )
        );

        $errorMiddleware->setDefaultErrorHandler(
            new Error(
                $this->getCallableResolver(),
                $this->getResponseFactory(),
                $logger
            )
        );
    }


    /**
     * @param $name
     * @return bool
     */
    public function has($name): bool
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

        $response = $this->resolve(Response::class);

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

            $controller = $this->getContainer()->get($className);

        } catch (\DI\NotFoundException $e) {
            $this->notFound();
        }

        if(!method_exists($controller, $methodName)){
            $this->notFound();
        }

        $method = new \ReflectionMethod($controller, $methodName);
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
        $dependency = $this->getContainer()->get($name);

        return is_callable($dependency) ? call_user_func_array($dependency, $params) : $dependency;
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


    /**
     * @throws HttpNotFoundException
     */
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
        return $this->resolve(Request::class)->withStatus($httpCode);
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
        $response = $this->resolve('response');

        if ($this->isConsole()) {
            $response = $response->withHeader('Content-type', 'text/plain');
            $response->getBody()->write($error . PHP_EOL . implode(PHP_EOL, $messages));
            return $response;
        }

        if ($this->resolve('request')->getHeaderLine('Accept') === 'application/json') {
            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($code);
            $response->getBody()->write(json_encode([
                'code'     => $code,
                'error'    => $error,
                'messages' => $messages,
            ]));
            return $response;
        }

        // Use application default handler
        if (!array_key_exists('errorHandler', $this->configs)) {
            throw new \Exception('No default error handler defined. Please configure it in application configurations.');
        }

        $response = is_callable($this->configs['errorHandler'])
            ? call_user_func($this->configs['errorHandler'], $code, $error, $messages)
            : (new $this->configs['errorHandler'])($code, $error, $messages);

        return $response;
    }

}
