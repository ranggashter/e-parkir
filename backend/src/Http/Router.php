<?php

namespace App\Http;

use App\Support\Request;
use App\Support\Response;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function patch(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function put(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array $handler, array $middlewares): void
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = Request::method();
        $requestUri = Request::uri();

        // Handle preflight OPTIONS
        if ($requestMethod === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $methodAllowed = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $requestUri, $matches)) {
                if ($route['method'] !== $requestMethod) {
                    $methodAllowed = true;
                    continue;
                }

                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                if (!in_array($requestMethod, ['GET', 'HEAD', 'OPTIONS'], true)) {
                    (new \App\Http\Middleware\CsrfMiddleware())->handle();
                }

                // Execute middleware chain
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        $middleware();
                    } elseif (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        $instance->handle();
                    }
                }

                [$controllerClass, $action] = $route['handler'];
                $controller = new $controllerClass();
                $controller->$action($params);
                return;
            }
        }

        if ($methodAllowed) {
            Response::error('Metode HTTP tidak diizinkan untuk route ini.', 405);
        }

        Response::error("Route tidak ditemukan: [{$requestMethod}] {$requestUri}", 404);
    }
}
