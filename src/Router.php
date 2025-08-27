<?php

namespace struktal\Router;

use JetBrains\PhpStorm\NoReturn;

class Router {
    private static string $pagesDirectory = "/";
    private static string $appUrl = "";
    private static string $appBaseUri = "/";
    private static string $staticDirectoryUri = "static/";

    /**
     * Sets the pages directory for the application
     * @param string $pagesDirectory Directory where the pages are located
     * @return void
     */
    public static function setPagesDirectory(string $pagesDirectory): void {
        self::$pagesDirectory = rtrim($pagesDirectory, "/") . "/";
    }

    /**
     * Sets the URL of the application
     * @param string $appUrl URL of the application
     * @return void
     */
    public static function setAppUrl(string $appUrl): void {
        self::$appUrl = rtrim($appUrl, "/") . "/";
    }

    /**
     * Sets the base URI for the application
     * @param string $appBaseUri Base URI of the application
     * @return void
     */
    public static function setAppBaseUri(string $appBaseUri): void {
        self::$appBaseUri = rtrim($appBaseUri, "/") . "/";
    }

    /**
     * Sets the URI for the static directory
     * @param string $staticDirectoryUri URI of the static directory
     * @return void
     */
    public static function setStaticDirectoryUri(string $staticDirectoryUri): void {
        self::$staticDirectoryUri = rtrim($staticDirectoryUri, "/") . "/";
    }

    private RouteRegistry $routeRegistry;

    private string $error400Route = "";
    private string $error404Route = "";

    public function __construct() {
        $this->routeRegistry = new RouteRegistry();
    }

    /**
     * Sets the redirect route for 400 errors
     * @param string $error400Route Redirect route
     * @return void
     */
    public function setError400Route(string $error400Route): void {
        $this->error400Route = $error400Route;
    }

    /**
     * Sets the redirect route for 404 errors
     * @param string $error404Route Redirect route
     * @return void
     */
    public function setError404Route(string $error404Route): void {
        $this->error404Route = $error404Route;
    }

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
    public function addRoute(array|string $methods, string $route, string $endpoint, string $name): void {
        $this->routeRegistry->register($methods, $route, $endpoint, $name);
    }

    public function getCalledURL(): string {
        return self::$appUrl . ltrim($_SERVER["REQUEST_URI"], "/");
    }

    /**
     * Returns the cleaned URI of the current request
     * This method removes the root directory from the URI and removes GET parameters after a question mark
     * @return string Cleaned URI
     */
    private function getCleanedURI(): string {
        $uri = $_SERVER["REQUEST_URI"];

        // Remove GET parameters after a question mark
        $uri = explode("?", $uri)[0];

        // Remove the root directory from the URI
        // This is required if the application is not located in the server's root directory
        if(str_starts_with($uri, self::$appBaseUri)) {
            $uri = substr($uri, strlen(self::$appBaseUri));
        }

        // Remove leading and trailing slashes
        $uri = trim($uri, "/");

        return $uri;
    }

    /**
     * Returns the import path for a file within the static directory
     * @param string $path File Path
     * @param bool $withHostUrl Whether to include the host URL in the returned path
     * @return string
     */
    public function staticFilePath(string $path, bool $withHostUrl = false): string {
        $url = "";
        if($withHostUrl) {
            $url = rtrim(self::$appUrl, "/");
        }
        $url .= self::$appBaseUri; // Not removing leading slash because the URL's trailing slash is removed above
        $url .= ltrim(self::$staticDirectoryUri, "/");
        $url .= ltrim($path, "/");
        return $url;
    }

    /**
     * Returns the URI for a route
     * @param string $name Name of the route
     * @param array $parameters GET parameters that should be added to the URI
     * @param bool $withHostUrl Whether to include the host URL in the returned path
     * @return string Route
     */
    public function generate(string $name, array $parameters = [], bool $withHostUrl = false): string {
        $urlPrefix = "";
        if($withHostUrl) {
            $urlPrefix = rtrim(self::$appUrl, "/");
        }
        $urlPrefix .= self::$appBaseUri; // Not removing leading slash because the URL's trailing slash is removed above

        $route = null;
        foreach($this->routeRegistry->getRoutes() as $method => $routes) {
            if(!isset($routes[$name])) {
                continue;
            }

            $route = $routes[$name];
            break;
        }
        if(!$route instanceof Route) {
            trigger_error("Could not find route with name \"$name\"", E_USER_WARNING);
            return $urlPrefix;
        }

        $generatedRoute = $route->generate($parameters);
        if($generatedRoute === null) {
            return $urlPrefix;
        }

        return $urlPrefix . ltrim($generatedRoute, "/");
    }

    /**
     * Redirects the user to the given path
     * @param string $redirectPath
     * @return void
     */
    #[NoReturn]
    public function redirect(string $redirectPath): void {
        header("Location: " . $redirectPath);
        exit;
    }

    /**
     * Returns the route that was called
     * @return Route|null
     */
    public function getCalledRoute(): Route|null {
        $method = $_SERVER["REQUEST_METHOD"];
        $uri = $this->getCleanedUri();

        if(!isset($this->routeRegistry->getRoutes()[$method])) {
            return null;
        }

        $routesForMethod = $this->routeRegistry->getRoutes()[$method];
        foreach($routesForMethod as $route) {
            if($route->matches($uri)) {
                return $route;
            }
        }

        // TODO: If $method is OPTIONS, return all allowed methods for the requested route

        return null;
    }

    /**
     * Returns the name of the route that was called
     * @return string|null
     */
    public function getCalledRouteName(): string|null {
        return $this->getCalledRoute()?->name;
    }

    /**
     * Redirects to the file that is registered for the requested route
     * This method also sets values in the $_GET Array
     * If no route is found or the file does not exist, the 404 page will be opened
     * If the required parameters are not valid, the 400 page will be opened
     * @return void
     */
    public function startRouter(): void {
        $route = $this->getCalledRoute();
        $uri = $this->getCleanedUri();

        if($route === null) {
            http_response_code(404);
            $this->redirect($this->error404Route);
        }

        $parameterValues = $route->getParameterValues($uri);
        if(!$route->setGETValues($parameterValues)) {
            http_response_code(400);
            $this->redirect($this->error400Route);
        }

        // Redirect to the file that is registered for the route
        $endpoint = self::$pagesDirectory . ltrim($route->endpoint, "/");
        if(str_ends_with($endpoint, ".php")) {
            if(file_exists($endpoint)) {
                include_once($endpoint);
            } else {
                trigger_error("Could not find file \"{$endpoint}\" for route \"{$route->name}\"", E_USER_WARNING);
                http_response_code(404);
                $this->redirect($this->error404Route);
            }
        } else {
            if(file_exists($endpoint)) {
                $this->sendContentTypeHeader($endpoint);
                readfile($endpoint);
                exit;
            } else {
                trigger_error("Could not find file \"{$endpoint}\" for route \"{$route->name}\"", E_USER_WARNING);
                http_response_code(404);
                $this->redirect($this->error404Route);
            }
        }
    }

    /**
     * Sends the correct Content-Type header for a given file
     * @param string $file File name or path
     * @return void
     */
    private function sendContentTypeHeader(string $file): void {
        $extensions = [
            "html" => "text/html",
            "css" => "text/css",
            "js" => "text/javascript",
            "json" => "application/json",
            "xml" => "application/xml",
            "png" => "image/png",
            "jpg" => "image/jpeg",
            "jpeg" => "image/jpeg",
            "gif" => "image/gif",
            "svg" => "image/svg+xml",
            "ico" => "image/x-icon",
            "ttf" => "font/ttf",
            "otf" => "font/otf",
            "woff" => "font/woff",
            "woff2" => "font/woff2",
            "eot" => "font/eot",
            "pdf" => "application/pdf",
            "zip" => "application/zip",
            "rar" => "application/x-rar-compressed",
            "7z" => "application/x-7z-compressed",
            "mp3" => "audio/mpeg",
            "wav" => "audio/wav",
            "ogg" => "audio/ogg",
            "mp4" => "video/mp4",
            "webm" => "video/webm",
            "avi" => "video/x-msvideo",
            "mpg" => "video/mpeg",
            "mpeg" => "video/mpeg",
            "flv" => "video/x-flv",
            "swf" => "application/x-shockwave-flash",
            "txt" => "text/plain",
            "csv" => "text/csv",
            "ics" => "text/calendar",
            "rtf" => "application/rtf",
            "doc" => "application/msword",
            "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "xls" => "application/vnd.ms-excel",
            "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "ppt" => "application/vnd.ms-powerpoint",
            "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation"
        ];

        foreach($extensions as $extension => $contentType) {
            if(str_ends_with($file, "." . $extension)) {
                header("Content-Type: $contentType");
                return;
            }
        }
    }
}
