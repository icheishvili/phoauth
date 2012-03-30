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
                } else {
                    $params[$key] = array($params[$key], $value);
                }
            } else {
                $params[$key] = $value;
            }
        }
        return $params;
    }
}
