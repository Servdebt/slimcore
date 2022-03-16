<?php

namespace Servdebt\SlimCore;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Servdebt\SlimCore\Container\Container;
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
use Slim\Psr7\Factory\ResponseFactory;

class App
{
    public string $appName;

    const DEVELOPMENT = 'development';
    const STAGING = 'staging';
    const PRODUCTION = 'production';

    public string $env = self::DEVELOPMENT;

    private ?\Slim\App $slim = null;

    private array $configs = [];

    private static ?self $instance = null;

    protected function __construct(ContainerInterface $container = null)
    {
        $this->appName = $this->isConsole() ? 'console' : 'http';

        if (!$container) {
            $container = (new Container())
                ->withAutoWiring()
                ->alias([
                    'request' => Request::class,
                    'response' => Response::class
                ]);
        }

        AppFactory::setContainer($container);
        $this->slim = AppFactory::create();

        $this->registerInContainer(Request::class, (ServerRequestCreatorFactory::create())->createServerRequestFromGlobals());
        $this->registerInContainer(Response::class, (new ResponseFactory)->createResponse());
    }

    public function loadEnv(string $path, string $filename = '.env', array $mandatoryConfigs = []): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($path, $filename);
        $dotenv->required($mandatoryConfigs);
        $dotenv->load();

        $this->env = App::env('APP_ENV');
    }

    public function setConfigs(array $configs): void
    {
        $this->configs = $configs;
    }

    public function run(): void
    {
        if (isset($this->configs['timezone'])) {
            date_default_timezone_set($this->configs['timezone']);
        }

        if (isset($this->configs['locale'])) {
            \Locale::setDefault($this->configs['locale']);
        }

        if (isset($this->configs['routerCacheFile']) && !empty($this->configs['routerCacheFile'])) {
            $routeCollector = $this->slim->getRouteCollector();
            $routeCollector->setCacheFile($this->configs['routerCacheFile']);
        }

        $this->addRoutingMiddleware();
        $this->registerMiddleware();
        $this->registerProviders();
        $this->registerErrorHandlers();

        $this->slim->run($this->request);
    }

    private function registerProviders(): void
    {
        $services = (array)$this->getConfig('services');
        foreach ($services as $serviceName => $service) {
            if (!isset($service['on']) || strpos($service['on'], $this->appName) !== false) {
                $service['provider']::register($this, $serviceName, $service['settings'] ?? []);
            }
        }
    }

    private function registerMiddleware(): void
    {
        $middlewares = array_reverse((array)$this->getConfig('middleware'));
        array_walk($middlewares, function($appName, $middleware) {
            if (strpos($appName, $this->appName) !== false) {
                $this->slim->add(new $middleware);
            }
        });
    }

    private function registerErrorHandlers(): void
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

    // Application Helpers //

    /**
     * Application Singleton Factory
     */
    final public static function instance(ContainerInterface $container = null): self
    {
        if (null === static::$instance) {
            static::$instance = new static($container);
        }

        return static::$instance;
    }

    public static function env(string $key, $default = '')
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->slim->{$name} = $value;
        } else {
            $this->registerInContainer($name, $value);
        }
    }

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

    public function __call($fn, $args = [])
    {
        if (method_exists($this->slim, $fn)) {
            return call_user_func_array([$this->slim, $fn], $args);
        }
        throw new \Exception('Method not found :: ' . $fn);
    }

    public function has($name): bool
    {
        return $this->getContainer()->has($name);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->slim->getContainer();
    }

    public function registerInContainer(string $name, $value): void
    {
        ($this->getContainer())->set($name, $value);
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

    public function isConsole(): bool
    {
        return php_sapi_name() === 'cli';
    }

    public function isEnvironment(string $environment)
    {
        return strtolower($this->env) === strtolower($environment);
    }

    public function determineEnvFilename($filename = null) : string
    {
        if ($this->isConsole()) {
            $cliCommandParts = (array)$GLOBALS['argv'];

            // remove cli.php
            array_shift($cliCommandParts);

            if (in_array($cliCommandParts[0] ?? '', [self::DEVELOPMENT, self::STAGING, self::PRODUCTION])) {
                return $filename.".".$cliCommandParts[0];
            }
        }

        return $filename;
    }

    /**
     * Generate a Url
     */
    public function url(string $url = '', ?bool $showIndex = null, bool $includeBaseUrl = true): string
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
     * Resolve and call a given class / method
     *
     * @param callable|array $classMethod [ClassNamespace, method]
     * @throws \ReflectionException|HttpNotFoundException
     */
    public function resolveRoute($classMethod, array $requestParams = []): Response
    {
        $className = $classMethod[0];
        $methodName = $classMethod[1];

        try {

            $controller = $this->getContainer()->get($className);

        } catch (NotFoundExceptionInterface $e) {
            if (str_contains($e->getMessage(), $className)) {
                $this->notFound();
            }

            throw $e;
        }

        if (!method_exists($controller, $methodName)) {
            $this->notFound();
        }

        $method = new \ReflectionMethod($controller, $methodName);
        $args = $this->resolveMethodDependencies($method, $requestParams);
        $ret = $method->invokeArgs($controller, $args);

        return $this->sendResponse($ret);
    }

    /**
     * return a response object
     *
     * @param mixed $resp
     *
     * @throws \ReflectionException
     */
    public function sendResponse($resp): Response
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

        $response->getBody()->write((string)$resp);

        return $response;
    }

    /**
     * @throws \ReflectionException
     */
    public function code(int $httpCode = 200): Response
    {
        return $this->resolve(Response::class)->withStatus($httpCode);
    }

    /**
     * @throws \ReflectionException|\Exception
     */
    public function error(int $status = 500, string $error = '', array $messages = [], $code = null): Response
    {
        $response = $this->resolve(Response::class);

        if ($this->isConsole()) {
            $response = $response->withHeader('Content-type', 'text/plain');
            $response->getBody()->write($error . PHP_EOL . implode(PHP_EOL, $messages));
            return $response;
        }

        if ($this->resolve(Request::class)->getHeaderLine('Accept') === 'application/json') {
            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);

            $response->getBody()->write(json_encode([
                'code'     => $code ?? $status,
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
            ? call_user_func($this->configs['errorHandler'], $status, $error, $messages)
            : (new $this->configs['errorHandler'])($status, $error, $messages);

        return $response;
    }

    // Container //

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

        $name = $param->getType() && !$param->getType()->isBuiltin() ? new \ReflectionClass($param->getType()->getName()) : null;
        if (!$name) {
            throw new \ReflectionException("Unable to resolve method param {$name}");
        }

        // try to resolve from container
        return $this->resolve($name);
    }

    /**
     * @throws HttpNotFoundException
     */
    public function notFound(): void
    {
        throw new HttpNotFoundException($this->request);
    }

}
