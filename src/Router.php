<?php

namespace struktal\Router;

use JetBrains\PhpStorm\NoReturn;

class Router {
    private static array $routes = [];

    private static string $pagesDirectory = "/";
    private static string $appUrl = "";
    private static string $appBaseUri = "/";
    private static string $staticDirectoryUri = "static/";
    private static string $error400Route = "";
    private static string $error404Route = "";

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

    /**
     * Sets the redirect route for 400 errors
     * @param string $error400Route Redirect route
     * @return void
     */
    public static function setError400Route(string $error400Route): void {
        self::$error400Route = $error400Route;
    }

    /**
     * Sets the redirect route for 404 errors
     * @param string $error404Route Redirect route
     * @return void
     */
    public static function setError404Route(string $error404Route): void {
        self::$error404Route = $error404Route;
    }

    /**
     * Registers a route
     * @param string $method HTTP method
     *                       Multiple methods can be separated with a pipe (|) character, without spaces or other symbols
     * @param string $route Route that should get called
     *                      GET parameters can be added to the route by using the following syntax: {type:name}
     *                      Supported types are b (boolean), f (float), i (integer) and s (string)
     *                      Names are used to identify the parameter within the $_GET array
     * @param string $routeTo File that should be executed when the route is called
     * @param string $name Name of the route
     * @return void
     */
    public static function addRoute(string $method, string $route, string $routeTo, string $name): void {
        // Retrieve Parameters from the Route
        $params = [];
        preg_match_all("/\{([bfis]:[a-zA-Z0-9]+)\}/", $route, $matches);
        foreach($matches[1] as $match) {
            $paramType = explode(":", $match)[0];
            $paramName = str_replace($paramType . ":", "", $match);
            $params[$paramName] = $paramType;
        }

        // Save the Route in the Routes Array
        $methods = explode("|", $method);
        foreach($methods as $method) {
            self::$routes[$method][$route] = [
                "route" => $route,
                "routeTo" => self::$pagesDirectory . $routeTo,
                "name" => $name,
                "params" => $params
            ];
        }
    }

    /**
     * Returns the URI for a route
     * @param string $name Name of the route
     * @param array $params GET parameters that should be added to the URI
     * @return string Route
     */
    public static function generate(string $name, array $params = [], bool $withHostUrl = false): string {
        $urlPrefix = $withHostUrl ? self::$appUrl : "";

        foreach(self::$routes as $method => $routes) {
            foreach($routes as $route => $routeData) {
                if($routeData["name"] == $name) {
                    // Found the Route
                    $requiredParams = array_keys($routeData["params"]);
                    foreach($params as $paramName => $paramValue) {
                        if(isset($routeData["params"][$paramName])) {
                            if($routeData["params"][$paramName] == "b" && is_bool($paramValue)) {
                                $paramValue = $paramValue ? "true" : "false";
                                $route = str_replace("{" . $routeData["params"][$paramName] . ":" . $paramName . "}", $paramValue, $route);
                                $requiredParams = array_diff($requiredParams, [$paramName]);
                            } else if($routeData["params"][$paramName] == "f" && is_float($paramValue)) {
                                $paramValue = floatval($paramValue);
                                $route = str_replace("{" . $routeData["params"][$paramName] . ":" . $paramName . "}", $paramValue, $route);
                                $requiredParams = array_diff($requiredParams, [$paramName]);
                            } else if($routeData["params"][$paramName] == "i" && is_int($paramValue)) {
                                $paramValue = intval($paramValue);
                                $route = str_replace("{" . $routeData["params"][$paramName] . ":" . $paramName . "}", $paramValue, $route);
                                $requiredParams = array_diff($requiredParams, [$paramName]);
                            } else if($routeData["params"][$paramName] == "s" && is_string($paramValue)) {
                                $paramValue = urlencode(strval($paramValue));
                                $route = str_replace("{" . $routeData["params"][$paramName] . ":" . $paramName . "}", $paramValue, $route);
                                $requiredParams = array_diff($requiredParams, [$paramName]);
                            }
                        }
                    }

                    if(sizeof($requiredParams) == 0) {
                        return $urlPrefix . self::$appBaseUri . ltrim($route, "/");
                    }
                }
            }
        }

        return $urlPrefix . self::$appBaseUri;
    }

    /**
     * Returns the cleaned URI of the current request
     * This method removes the root directory from the URI and removes GET parameters after a question mark
     * @return string Cleaned URI
     */
    private static function getCleanedUri(): string {
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
     * Returns the route that was called
     * @return array|null
     */
    public static function getCalledRoute(): array|null {
        $method = $_SERVER["REQUEST_METHOD"];
        $uri = self::getCleanedUri();

        $foundRoute = null;
        if(isset(self::$routes[$method])) {
            foreach(self::$routes[$method] as $routeData) {
                $route = $routeData["route"];
                $route = trim($route, "/");
                $regex = "";
                $routeParts = explode("/", $route);
                // Loop over all parts of the route and create a regex
                foreach($routeParts as $part) {
                    if(preg_match("/\{([bfis]:[a-zA-Z0-9]+)\}/", $part)) {
                        // The current route part is a parameter
                        // Add regex for the corresponding parameter type
                        $part = trim($part, "{}");
                        $paramType = explode(":", $part)[0];
                        switch($paramType) {
                            case "b":
                                $regex .= "true|false\/";
                                break;
                            case "f":
                                $regex .= "[\d]+(\.[\d]+)?\/";
                                break;
                            case "i":
                                $regex .= "[\d]+\/";
                                break;
                            case "s":
                                $regex .= ".+\/";
                                break;
                        }
                    } else {
                        // The current route part is no parameter
                        // Simply add the part to the regex
                        $regex .= $part . "\/";
                    }
                }
                if(str_ends_with($regex, "\/")) {
                    $regex = substr($regex, 0, strlen($regex) - 2);
                }

                if(preg_match("#^" . $regex . "$#i", $uri)) {
                    // The current route matches the request
                    $foundRoute = $routeData;
                }
            }
        }

        return $foundRoute;
    }

    /**
     * Redirects to the file that is registered for the requested route
     * This method also sets values in the $_GET Array
     * If no route is found or the file does not exist, the 404 page will be opened
     * If the required parameters are not valid, the 400 page will be opened
     * @return void
     */
    public function startRouter(): void {
        $foundRoute = self::getCalledRoute();
        $uri = self::getCleanedUri();

        if($foundRoute === null) {
            http_response_code(404);
            self::redirect(self::$error404Route);
        }

        $route = $foundRoute["route"];
        $route = trim($route, "/");
        $routeTo = $foundRoute["routeTo"];

        // Set the GET parameters
        // Loop over all parts of the route
        foreach(explode("/", $route) as $key => $part) {
            if(preg_match("/\{([bfis]:[a-zA-Z0-9]+)\}/", $part)) {
                // The current route part is a parameter
                // Retrieve the parameter type and name from the route part and the value from the URI
                $part = trim($part, "{}");
                $paramType = explode(":", $part)[0];
                $paramName = str_replace($paramType . ":", "", $part);
                $paramValue = explode("/", $uri)[$key];

                if(self::getParameterFromString($paramValue, $paramType) !== null) {
                    $paramValue = self::getParameterFromString($paramValue, $paramType);
                } else {
                    trigger_error("Could not parse parameter \"{$paramName}\" with value \"{$paramValue}\" for route \"{$route}\"", E_USER_WARNING);
                    http_response_code(400);
                    self::redirect(self::$error400Route);
                }

                $_GET[$paramName] = urldecode($paramValue);
            }
        }

        // Redirect to the file that is registered for the route
        if(str_ends_with($routeTo, ".php")) {
            if(file_exists($routeTo)) {
                include_once($routeTo);
            } else {
                trigger_error("Could not find file \"{$routeTo}\" for route \"{$route}\"", E_USER_WARNING);
                http_response_code(404);
                self::redirect(self::$error404Route);
            }
        } else {
            if(file_exists($routeTo)) {
                $this->sendContentTypeHeader($routeTo);
                readfile($routeTo);
                exit;
            } else {
                trigger_error("Could not find file \"{$routeTo}\" for route \"{$route}\"", E_USER_WARNING);
                http_response_code(404);
                self::redirect(self::$error404Route);
            }
        }
    }

    /**
     * Returns the URL that was called
     * @return string
     */
    public static function getCalledURL(): string {
        return self::$appUrl . ltrim($_SERVER["REQUEST_URI"], "/");
    }

    /**
     * Returns the name of the route that was called
     * @return string|null
     */
    public static function getCalledRouteName(): string|null {
        $foundRoute = self::getCalledRoute();
        if($foundRoute !== null) {
            return $foundRoute["name"];
        }
        return null;
    }

    /**
     * Returns the import path for a file within the static directory
     * @param string $path File Path
     * @return string
     */
    public static function staticFilePath(string $path): string {
        return self::$appBaseUri . self::$staticDirectoryUri . trim($path, "/");
    }

    /**
     * If parsing is possible, returns the parameter of the corresponding type from a string
     * @param mixed $value Value that should be parsed
     * @param string $parameter Type of the parameter (b, f, i, s)
     * @return mixed|null Parsed parameter or null if parsing is not possible
     */
    private static function getParameterFromString(mixed $value, string $parameter): mixed {
        switch($parameter) {
            case "b":
                if(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
                break;
            case "f":
                if(filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    return floatval($value);
                }
                break;
            case "i":
                if(filter_var($value, FILTER_VALIDATE_INT)) {
                    return intval($value);
                }
                break;
            case "s":
                return urldecode(strval($value));
        }

        return null;
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

    /**
     * Redirects the user to the given path
     * @param string $redirectPath
     * @return void
     */
    #[NoReturn]
    public static function redirect(string $redirectPath): void {
        header("Location: " . $redirectPath);
        exit;
    }
}
