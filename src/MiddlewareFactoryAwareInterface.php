<?php

namespace Componenta\Http\Middleware;

/**
 * Interface for resolvers that need access to MiddlewareFactory.
 *
 * Resolvers implementing this interface will automatically receive
 * the MiddlewareFactory instance when constructed.
 */
interface MiddlewareFactoryAwareInterface
{
    /**
     * Sets the middleware factory instance.
     *
     * @param MiddlewareFactory $factory The middleware factory
     * @return void
     */
    public function setMiddlewareFactory(MiddlewareFactory $factory): void;
}