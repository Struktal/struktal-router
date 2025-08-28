<?php

namespace struktal\Router;

class RouteRegistry {
    private array $routes = [];
    private array $routeNames = [];

    /**
     * Registers a route
     * @param array|string $methods HTTP methods
     *                              Multiple methods can be provided as an array or separated with a pipe (|) character, without spaces or other symbols
     * @param string $route Route that should get called
     *                      GET parameters can be added to the route by using the following syntax: {type:name}
     *                      Supported types are b (boolean), f (float), i (integer) and s (string)
     *                      Names are used to identify the parameter within the $_GET array
     * @param string $endpoint File that should be executed when the route is called
     * @param string $name Name of the route
     * @return void
     */
    public function register(array|string $methods, string $route, string $endpoint, string $name): void {
        if(in_array($name, $this->routeNames)) {
            throw new \InvalidArgumentException("Route name \"$name\" already registered");
        }

        if(!is_array($methods)) {
            $methods = explode("|", $methods);
        }

        foreach($methods as $method) {
            $method = strtoupper($method);
            if(!in_array($method, ["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS", "HEAD"])) {
                throw new \InvalidArgumentException("Invalid HTTP method \"$method\"");
            }

            $this->routes[$method][$name] = new Route($route, $endpoint, $name);
        }
    }

    public function getRoutes(): array {
        return $this->routes;
    }
}
