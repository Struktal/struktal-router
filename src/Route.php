<?php

namespace struktal\Router;

class Route {
    public string $route;
    public string $endpoint;
    public string $name;
    public array $parameters;

    public function __construct(string $route, string $endpoint, string $name) {
        $this->route = $route;
        $this->endpoint = $endpoint;
        $this->name = $name;

        // Retrieve parameters from the route
        $this->parameters = [];
        preg_match_all(RouteParameter::paramCheckRegex(), $route, $matches);
        foreach($matches[1] as $match) {
            $paramType = explode(":", $match)[0];
            $paramName = str_replace($paramType . ":", "", $match);
            $this->parameters[$paramName] = RouteParameter::fromRoutePart("{" . $match . "}");
        }
    }

    public function generate(array $parameters = []): string|null {
        $requiredParameters = array_keys($this->parameters);
        $route = $this->route;

        foreach($parameters as $parameterName => $parameterValue) {
            if(!isset($this->parameters[$parameterName])) {
                trigger_error("Unknown parameter \"$parameterName\" for route \"{$this->name}\"", E_USER_WARNING);
                continue;
            }

            $parameterType = $this->parameters[$parameterName];
            if(!$parameterType instanceof RouteParameter || !$parameterType->isValid($parameterValue)) {
                trigger_error("Invalid parameter type for parameter \"$parameterName\": Expected {$parameterType->name}, got " . gettype($parameterValue), E_USER_WARNING);
                continue;
            }

            $parameterValue = $parameterType->getUrlEncodedValue($parameterValue);
            $route = str_replace("{" . $parameterType->value . ":" . $parameterName . "}", $parameterValue, $route);
            unset($requiredParameters[array_search($parameterName, $requiredParameters)]);
        }

        if(count($requiredParameters) > 0) {
            trigger_error("Missing parameters for route \"{$this->name}\": " . implode(", ", $requiredParameters), E_USER_WARNING);
            return null;
        }

        return $route;
    }

    public function generateRegex(): string {
        $regex = "";
        $routeParts = explode("/", $this->route);

        foreach($routeParts as $part) {
            if(preg_match(RouteParameter::paramCheckRegex(), $part)) {
                // The current part is a parameter
                // Add regex for the corresponding parameter type
                $routeParameter = RouteParameter::fromRoutePart($part);
                $regex .= "({$routeParameter->regex()})";
            } else {
                // The current route part is no parameter
                // Simply add the part to the regex
                $regex .= $part;
            }

            $regex .= "\/";
        }

        if(str_ends_with($this->route, "/")) {
            $regex .= "?";
        } else {
            $regex = rtrim($regex, "\/");
        }

        return $regex;
    }

    public function matches(string $calledRoute): bool {
        return preg_match("#^{$this->generateRegex()}$#i", $calledRoute);
    }

    public function getParameterValues(string $calledRoute): array {
        $matches = preg_match_all("#^{$this->generateRegex()}$#i", $calledRoute, $groups);
        if(!$matches) {
            return [];
        }

        $parameterValues = [];
        foreach($this->parameters as $i => $parameter) {
            $parameterValues[$parameter["name"]] = $groups[$i + 1][0];
        }
        return $parameterValues;
    }

    public function setGETValues(array $parameterValues): bool {
        $requiredParameters = array_keys($this->parameters);

        foreach($parameterValues as $parameterName => $parameterValue) {
            if(!isset($this->parameters[$parameterName])) {
                trigger_error("Unknown parameter \"$parameterName\" for route \"{$this->name}\"", E_USER_WARNING);
                return false;
            }

            $parameterType = $this->parameters[$parameterName];
            if(!$parameterType instanceof RouteParameter) {
                return false;
            }

            $parsedValue = $parameterType->getValueFromString($parameterValue);
            if($parsedValue === null) {
                trigger_error("Could not parse parameter \"$parameterName\" with value \"$parameterValue\" to type {$parameterType->name} for route {$this->name}", E_USER_WARNING);
                return false;
            }

            $_GET[$parameterName] = $parsedValue;

            unset($requiredParameters[array_search($parameterName, $requiredParameters)]);
        }

        if(count($requiredParameters) > 0) {
            trigger_error("Missing parameters for route \"{$this->name}\": " . implode(", ", $requiredParameters), E_USER_WARNING);
            return false;
        }

        return true;
    }
}
