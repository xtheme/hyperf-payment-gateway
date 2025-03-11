<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Hyperf\Contract\ContainerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ServerRequestInterface;

class BaseMiddleware
{
    protected ContainerInterface $container;

    protected ServerRequestInterface $request;

    protected HttpResponse $response;

    public function __construct(ContainerInterface $container, ServerRequestInterface $request, HttpResponse $response)
    {
        $this->container = $container;
        $this->request = $request;
        $this->response = $response;
    }

    protected function setAttribute($key, $value)
    {
        return Context::override(ServerRequestInterface::class, function (ServerRequestInterface $request) use ($key, $value) {
            return $request->withAttribute($key, $value);
        });
    }
}
