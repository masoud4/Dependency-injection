<?php
namespace Masoud4\HttpTools\Container;

// Mimicking Psr\Container\ContainerInterface but without the direct PSR dependency for now.
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws \Psr\Container\NotFoundExceptionInterface No entry was found for this identifier.
     * @throws \Psr\Container\ContainerExceptionInterface Error while retrieving the entry.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool;
}
