<?php namespace PhoAuth;

/**
 * Provides useful utilities in the PhoAuth package.
 */
class Utils
{
    /**
     * Convenience function to get all HTTP request headers from the $_SERVER
     * superglobal variable.
     *
     * @static
     * @return array
     */
    public static function getServerHeaders()
    {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') !== 0) {
                continue;
            }

            $headerName = implode('-', array_slice(array_map(function($part)
            {
                return ucfirst(strtolower($part));
            }, explode('_', $key)), 1));

            $headers[$headerName] = $value;
        }

        return $headers;
    }

    /**
     * Convenience function to derive the scheme based on the $_SERVER
     * superglobal.
     *
     * @static
     * @return string
     */
    public static function getServerScheme()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
            return 'https';
        }
        return 'http';
    }

    /**
     * Convenience function to fetch the host as defined in the $_SERVER
     * superglobal.
     *
     * @static
     * @return string
     */
    public static function getServerHost()
    {
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * Convenience function to get the port as defined in the $_SERVER
     * superglobal.
     *
     * @static
     * @return integer
     */
    public static function getServerPort()
    {
        return (int)$_SERVER['SERVER_PORT'];
    }

    /**
     * Convenience function to fetch the HTTP request method as defined in
     * the $_SERVER superglobal.
     *
     * @static
     * @return string
     */
    public static function getServerMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Convenience function to get the URI (path + query) from the
     * $_SERVER superglobal.
     *
     * @static
     * @return string
     */
    public static function getServerRequestUri()
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * Convenience function to get the input body that was sent to PHP
     * (usually the HTTP request body).
     *
     * @static
     * @return string
     */
    public static function getServerInputBody()
    {
        return file_get_contents('php://input');
    }

    /**
     * Helper function to correctly parse a query string. This is
     * something that PHP does not implement correctly (the correct
     * behavior is to build lists of values for the same key instead
     * of overriding the previous value).
     *
     * @param string $input
     *
     * @return array
     */
    public static function parseQuery($input)
    {
        $parts = explode('&', $input);
        $params = array();
        foreach ($parts as $part) {
            $subparts = explode('=', $part);
            $key = rawurldecode($subparts[0]);
            $value = count($subparts) > 1 ? rawurldecode($subparts[1]) : '';
            if (isset($params[$key])) {
                if (is_array($params[$key])) {
                    $params[$key][] = $value;
                } elseif (strlen($key) && strlen($value)) {
                    $params[$key] = array($params[$key], $value);
                }
            } elseif (strlen($key) && strlen($value)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    /**
     * Take variadic arguments and intelligently merge all of their
     * keys.
     *
     * @param [array] ...
     *
     * @return array
     */
    public static function mergeParams()
    {
        $merged = array();

        foreach (func_get_args() as $arg) {
            if (!is_array($arg)) {
                $merged[] = $arg;
            } elseif (array_keys($arg) === range(0, count($arg) - 1)) {
                foreach ($arg as $value) {
                    $merged[] = $value;
                }
            } else {
                foreach ($arg as $key => $value) {
                    if (isset($merged[$key])) {
                        $merged[$key] = self::mergeParams($merged[$key], $value);
                    } else {
                        $merged[$key] = $value;
                    }
                }
            }
        }

        return $merged;
    }

    /**
     * Automaton states.
     */
    const PARSING_STATE_NONE = 0;
    const PARSING_STATE_KEY = 1;
    const PARSING_STATE_QUOTED_KEY = 2;
    const PARSING_STATE_QUOTED_KEY_ESCAPED_CHAR = 3;
    const PARSING_STATE_AFTER_KEY_BEFORE_VALUE = 4;
    const PARSING_STATE_VALUE = 5;
    const PARSING_STATE_QUOTED_VALUE = 6;
    const PARSING_STATE_QUOTED_VALUE_ESCAPED_CHAR = 7;

    /**
     * Used to parse complicated headers (in the case of auth). See
     * the unit tests for this class for examples of what those are.
     * The parsing here is based on Automata Theory.
     *
     * @param string $input
     *
     * @return array
     */
    public static function parseHeader($input)
    {
        $params = array();
        $key = $value = '';
        $state = self::PARSING_STATE_NONE;
        for ($i = 0; $i < strlen($input); $i++) {
            $c = $input[$i];
            switch ($state) {
            case self::PARSING_STATE_NONE:
                if (strlen($key) || strlen($value)) {
                    if (isset($params[$key])) {
                        $value = array($params[$key], $value);
                    }
                    $params[$key] = $value;
                    $key = $value = '';
                }

                switch ($c) {
                case ' ':
                    break;
                case '\t':
                    break;
                case '\n':
                    break;
                case ',':
                    break;
                case '"':
                    $state = self::PARSING_STATE_QUOTED_KEY;
                    break;
                default:
                    $key .= $c;
                    $state = self::PARSING_STATE_KEY;
                }
                break;
            case self::PARSING_STATE_KEY:
                switch ($c) {
                case '=':
                    $key = trim($key);
                    $state = self::PARSING_STATE_AFTER_KEY_BEFORE_VALUE;
                    break;
                default:
                    $key .= $c;
                }
                break;
            case self::PARSING_STATE_QUOTED_KEY:
                switch ($c) {
                case '\\':
                    $state = self::PARSING_STATE_QUOTED_KEY_ESCAPED_CHAR;
                    break;
                case '"':
                    $state = self::PARSING_STATE_AFTER_KEY_BEFORE_VALUE;
                    break;
                default:
                    $key .= $c;
                }
                break;
            case self::PARSING_STATE_QUOTED_KEY_ESCAPED_CHAR:
                switch ($c) {
                case '\\':
                    $key .= '\\';
                    break;
                case '"':
                    $key .= '"';
                    break;
                }
                $state = self::PARSING_STATE_QUOTED_KEY;
                break;
            case self::PARSING_STATE_AFTER_KEY_BEFORE_VALUE:
                switch ($c) {
                case ' ':
                    break;
                case '\t':
                    break;
                case '\n':
                    break;
                case '=':
                    break;
                case '"':
                    $state = self::PARSING_STATE_QUOTED_VALUE;
                    break;
                default:
                    $value .= $c;
                    $state = self::PARSING_STATE_VALUE;
                }
                break;
            case self::PARSING_STATE_VALUE:
                if ($c == ',') {
                    $value = trim($value);
                    $state = self::PARSING_STATE_NONE;
                } else {
                    $value .= $c;
                }
                break;
            case self::PARSING_STATE_QUOTED_VALUE:
                switch ($c) {
                case '\\':
                    $state = self::PARSING_STATE_QUOTED_VALUE_ESCAPED_CHAR;
                    break;
                case '"':
                    $state = self::PARSING_STATE_NONE;
                    break;
                default:
                    $value .= $c;
                }
                break;
            case self::PARSING_STATE_QUOTED_VALUE_ESCAPED_CHAR:
                switch ($c) {
                case '\\':
                    $value .= '\\';
                    break;
                case '"':
                    $value .= '"';
                    break;
                }
                $state = self::PARSING_STATE_QUOTED_VALUE;
                break;
            }
        }

        if (strlen($key) || strlen($value)) {
            if (isset($params[$key])) {
                $value = array($params[$key], $value);
            }
            $params[$key] = $value;
        }

        return $params;
    }
}
