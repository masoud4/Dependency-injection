<?php
namespace Masoud4\HttpTools\Container;

use Masoud4\HttpTools\Container\Exception\ContainerException;
use Masoud4\HttpTools\Container\Exception\NotFoundException;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

class Container implements ContainerInterface
{
    /**
     * @var array<string, mixed> $definitions Stores service definitions (class names, callables, config arrays).
     */
    private array $definitions = [];

    /**
     * @var array<string, mixed> $singletons Stores already resolved singleton instances.
     */
    private array $singletons = [];

    /**
     * @param array<string, mixed> $definitions Optional initial service definitions.
     */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions;
        // Make the container itself accessible
        $this->definitions[ContainerInterface::class] = $this;
        $this->definitions[self::class] = $this;
    }

    /**
     * Binds an identifier to a definition.
     *
     * @param string $id The identifier (e.g., interface FQCN, class FQCN, or alias).
     * @param mixed $definition The definition:
     * - string (FQCN): A class to instantiate.
     * - callable (function/closure): A factory that returns the instance.
     * - array: Configuration array for advanced binding (e.g., ['class' => FQCN, 'singleton' => true, 'arguments' => [...] ])
     * - mixed: A direct value to return for the ID (e.g., a config array, a simple string).
     * @return void
     */
    public function set(string $id, mixed $definition): void
    {
        $this->definitions[$id] = $definition;
        // If an instance was already resolved as a singleton, clear it to force re-resolution.
        unset($this->singletons[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        if ($this->has($id) && isset($this->singletons[$id])) {
            return $this->singletons[$id]; // Return existing singleton
        }

        if (!$this->has($id)) {
            // Attempt to auto-resolve if no explicit definition is found and it looks like a class.
            if (class_exists($id) || interface_exists($id)) {
                if (interface_exists($id)) {
                    throw new NotFoundException("No definition found for interface: {$id}.");
                }
                $definition = $id; // Treat the ID itself as the class to instantiate
            } else {
                throw new NotFoundException("No definition found for id: {$id}");
            }
        } else {
            $definition = $this->definitions[$id];
        }

        try {
            $instance = $this->resolve($definition, $id);
        } catch (NotFoundException $e) {
            // Re-throw NotFoundException directly
            throw $e;
        } catch (Throwable $e) {
            // Wrap other exceptions for context
            throw new ContainerException("Error while resolving '{$id}': " . $e->getMessage(), 0, $e);
        }

        // If definition specified as singleton, store it.
        // Or if it was an auto-resolved concrete class, store as singleton (common default behavior).
        $isSingleton = (is_array($definition) && ($definition['singleton'] ?? false)) || !is_array($definition); // Default concrete classes to singleton
        
        if ($isSingleton) { // If it's a singleton, store the instance
             $this->singletons[$id] = $instance;
        }

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    /**
     * Resolves a definition into an instance.
     * This method handles various definition types (class string, callable, config array).
     *
     * @param mixed $definition The service definition.
     * @param string $id The ID being resolved (for error context).
     * @return mixed The resolved instance.
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolve(mixed $definition, string $id): mixed
    {
        // This handles cases like UserService::class => 'userServiceDefinition'.
        if (is_string($definition) && $this->has($definition)) {
            return $this->get($definition); // Recursively get the actual service
        }

        if (is_string($definition) && class_exists($definition)) {
            // Definition is a class name (e.g., 'App\Services\Mailer')
            return $this->resolveClass($definition);
        } elseif (is_callable($definition)) {
            // Definition is a callable factory (e.g., function() { return new MyClass(); })
            return $this->resolveCallable($definition);
        } elseif (is_array($definition)) {
            // Definition is a configuration array
            return $this->resolveConfigArray($definition, $id);
        } else {
            // Definition is a direct value (e.g., a string, an integer, an array of config)
            return $definition;
        }
    }

    /**
     * Resolves a class definition.
     *
     * @param string $class FQCN of the class to instantiate.
     * @return object
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveClass(string $class): object
    {
        try {
            $reflector = new ReflectionClass($class);
        } catch (Throwable $e) {
            throw new ContainerException("Class {$class} cannot be found or loaded: " . $e->getMessage(), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class {$class} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $class; // No constructor, just instantiate
        }

        $dependencies = $this->resolveMethodDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolves a callable (factory) definition.
     *
     * @param callable $factory The factory callable.
     * @return mixed The instance returned by the factory.
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveCallable(callable $factory): mixed
    {
        // FIX: Use fully qualified names for ReflectionMethod and ReflectionFunction
        $reflector = is_array($factory) ? new \ReflectionMethod($factory[0], $factory[1]) : new \ReflectionFunction($factory);
        $dependencies = $this->resolveMethodDependencies($reflector->getParameters());
        return call_user_func_array($factory, $dependencies);
    }

    /**
     * Resolves a service defined by a configuration array.
     * Expected format: ['class' => FQCN, 'arguments' => [...], 'singleton' => bool, 'factory' => callable]
     *
     * @param array $config The configuration array.
     * @param string $id The ID being resolved.
     * @return mixed
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveConfigArray(array $config, string $id): mixed
    {
        if (isset($config['factory']) && is_callable($config['factory'])) {
            return $this->resolveCallable($config['factory']);
        }

        if (!isset($config['class']) || !is_string($config['class'])) {
            throw new ContainerException("Invalid array definition for '{$id}'. 'class' key missing or invalid.");
        }

        $class = $config['class'];
        try {
            $reflector = new ReflectionClass($class);
        } catch (Throwable $e) {
            throw new ContainerException("Class {$class} defined for '{$id}' cannot be found or loaded: " . $e->getMessage(), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class {$class} defined for '{$id}' is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        $dependencies = [];

        if ($constructor !== null) {
            $dependencies = $this->resolveMethodDependencies($constructor->getParameters(), $config['arguments'] ?? []);
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolves dependencies for a method (constructor or factory callable).
     *
     * @param ReflectionParameter[] $parameters The parameters of the method.
     * @param array $providedArgs Optional arguments explicitly provided in config.
     * @return array Resolved arguments.
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveMethodDependencies(array $parameters, array $providedArgs = []): array
    {
        $dependencies = [];
        foreach ($parameters as $param) {
            $paramName = $param->getName();

            // 1. Check for explicitly provided arguments by name
            if (array_key_exists($paramName, $providedArgs)) {
                // If it's a service ID string, resolve it. Otherwise, use the literal value.
                $argValue = $providedArgs[$paramName];
                // Check if the provided argument is itself a service ID that needs resolving
                if (is_string($argValue) && $this->has($argValue)) {
                     $dependencies[] = $this->get($argValue);
                } else {
                     $dependencies[] = $argValue;
                }
                continue;
            }
            
            // 2. Check for type-hinted classes/interfaces
            $type = $param->getType();
            if ($type !== null && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($this->has($typeName) || class_exists($typeName) || interface_exists($typeName)) {
                    $dependencies[] = $this->get($typeName); // Recursively resolve the dependency
                    continue;
                }
            }

            // 3. Fallback to default value if available
            if ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
                continue;
            }

            // 4. Handle nullable parameters
            if ($type !== null && $type->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            // If none of the above, we can't resolve this dependency
            throw new ContainerException("Unresolvable dependency for parameter '{$paramName}' in " . $param->getDeclaringFunction()->getName());
        }
        return $dependencies;
    }
}
