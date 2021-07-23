<?php

namespace Servdebt\SlimCore\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface
{

    private array $container = [];
    private array $alias = [];
    private bool $autoWiring = false;


    /**
     * set auto wiring
     *
     * @return self
     */
    public function withAutoWiring()
    {
        $this->autoWiring = true;

        return $this;
    }


    /**
     * Adds to the container
     *
     * @param string $id
     * @param mixed $value
     *
     * @return self
     */
    public function set(string $id, $value)
    {
        $this->container[$id] = $value;

        return $this;
    }


    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed resolved object
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @throws NotFoundExceptionInterface  No entry was found for this identifier.
     */
    public function get(string $id)
    {
        if (isset($this->alias[$id])) {
            $id = $this->alias[$id];
        }

        if (!$this->has($id)) {

            if (!$this->autoWiring) {
                throw new NotFoundException(sprintf('Could not find container definition for %s', $id));
            }

            try {
                $class = new \ReflectionClass($id);
                if (!$class->isInstantiable()) {
                    throw new NotFoundException(sprintf('Could not find container definition for %s', $id));
                }

                $constructorArgs = $this->resolveMethodDependencies($class->getConstructor());
                return $class->newInstanceArgs($constructorArgs);

            } catch (\ReflectionException $e) {
                throw new NotFoundException(sprintf('Could not find container definition for %s', $id));
            }
        }

//        if (is_callable($this->container[$id])) {
//            $this->container[$id] = $this->resolve($id);
//        }

        return $this->container[$id];
    }


    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        if (isset($this->alias[$id])) {
            return isset($this->container[$this->alias[$id]]);
        }

        return isset($this->container[$id]);
    }


    /**
     * alias
     *
     * @param array $aliases
     *
     * @return self
     */
    public function alias(array $aliases): self
    {
        foreach($aliases as $id => $alias){
            $this->alias[$id] = $alias;
        }

        return $this;
    }


    /**
     * resolve dependencies for a given class method
     *
     * @param \ReflectionMethod $method
     * @return array
     */
    private function resolveMethodDependencies(\ReflectionMethod $method)
    {
        return array_map(function($dependency) {
            return $this->resolveDependency($dependency);
        }, $method->getParameters());
    }


    /**
     * resolve a dependency parameter
     *
     * @param \ReflectionParameter $param
     * @return mixed
     *
     * @throws \ReflectionException
     */
    private function resolveDependency(\ReflectionParameter $param)
    {
        // param is instantiable
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        $obj = $param->getType() && !$param->getType()->isBuiltin() ? $this->get($param->getType()->getName()) : null;

        if (!$obj) {
            throw new \ReflectionException("Unable to resolve method param {$obj}");
        }

        return $obj;
    }

}