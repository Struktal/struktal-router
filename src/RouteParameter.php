<?php

namespace struktal\Router;

enum RouteParameter: string {
    case BOOLEAN = "b";
    case FLOAT = "f";
    case INTEGER = "i";
    case STRING = "s";

    public static function paramCheckRegex(): string {
        $allParamShortcodes = implode("", array_map(fn($case) => $case->value, self::cases()));
        return "/\{([{$allParamShortcodes}]:[a-zA-Z0-9]+)\}/";
    }

    public static function fromRoutePart(string $part): ?self {
        if(preg_match(self::paramCheckRegex(), $part)) {
            $part = trim($part, "{}");
            $paramType = explode(":", $part)[0];
            return match($paramType) {
                "b" => self::BOOLEAN,
                "f" => self::FLOAT,
                "i" => self::INTEGER,
                "s" => self::STRING,
                default => null,
            };
        }
        return null;
    }

    public function regex(): string {
        return match($this) {
            self::BOOLEAN => "true|false",
            self::FLOAT => "[\d]+(\.[\d]+)?",
            self::INTEGER => "[\d]+",
            self::STRING => "(?:[A-Za-z0-9._~\-]|%[0-9A-Fa-f]{2})+",
        };
    }

    public function isValid(mixed $parameterValue): bool {
        return match($this) {
            self::BOOLEAN => is_bool($parameterValue),
            self::FLOAT => is_float($parameterValue),
            self::INTEGER => is_int($parameterValue),
            self::STRING => is_string($parameterValue),
        };
    }

    public function getUrlEncodedValue(mixed $parameterValue): string {
        return match($this) {
            self::BOOLEAN => $parameterValue ? "true" : "false",
            self::FLOAT, self::INTEGER => (string) $parameterValue,
            self::STRING => rawurlencode($parameterValue),
        };
    }

    /**
     * If parsing is possible, returns the parameter of the corresponding type from a string
     * @param mixed $value Value that should be parsed
     * @return mixed|null Parsed parameter or null if parsing is not possible
     */
    public function getValueFromString(string $value): mixed {
        switch($this) {
            case self::BOOLEAN:
                if(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
                break;
            case self::FLOAT:
                if(filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    return floatval($value);
                }
                break;
            case self::INTEGER:
                if(filter_var($value, FILTER_VALIDATE_INT)) {
                    return intval($value);
                }
                break;
            case self::STRING:
                return rawurldecode(strval($value));
        }

        return null;
    }
}
