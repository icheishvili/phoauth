<?php namespace PhoAuth;

use PhoAuth\Utils;

class Signer
{
    /**
     * http or https
     *
     * @var string
     */
    private $_scheme = 'http';

    /**
     * The request method.
     *
     * @var string
     */
    private $_method = 'GET';

    /**
     * The host the HTTP request is made to (server).
     *
     * @var string
     */
    private $_host = 'localhost';

    /**
     * The port the HTTP request is made to (server).
     *
     * @var integer
     */
    private $_port = 80;

    /**
     * The HTTP request path info.
     *
     * @var string
     */
    private $_path = '/';

    /**
     * The HTTP request headers.
     *
     * @var string
     */
    private $_headers = array();

    /**
     * The data that was received in the body of the request.
     *
     * @var string
     */
    private $_body = '';

    /**
     * The OAuth realm (optional).
     *
     * @var string
     */
    private $_realm = null;

    /**
     * All of the parameters that were sent in with the request.
     *
     * @var array
     */
    private $_params = array();

    /**
     * The parameters given in the (optional) OAuth Authorization header.
     *
     * @var array
     */
    private $_authHeaderParams = array();

    /**
     * A callback that validates the nonce/timestamp combination (OAuth
     * parameters).
     *
     * @var callback
     */
    private $_timestampAndNonceValidator;

    /**
     * A callback that finds the secret key for a consumer key.
     *
     * @var callback
     */
    private $_consumerSecretFinder;

    /**
     * A callback that finds the secret key for an OAuth token.
     *
     * @var callback
     */
    private $_tokenSecretFinder;

    /**
     * A mapping of signature types to signature generators.
     *
     * @var array
     */
    private $_signatureGenerators = array();

    /**
     * Set the object up based on all of the HTTP request parts.
     *
     * @param string $scheme
     * @param string $method
     * @param string $host
     * @param string $port
     * @param string $uri
     * @param array  $headers
     * @param string $body
     */
    public function __construct(
        $scheme, $method, $host, $port, $uri, $headers = array(), $body = '')
    {
        $this->_initDefaultCallbacks();

        // sometimes the host is given in the form 192.168.2.1:8000
        $normalizedHost = reset((explode(':', $host)));
        $parsedUri      = parse_url($uri);
        if (isset($parsedUri['query'])) {
            $parsedQuery = Utils::parseQuery($parsedUri['query']);
        } else {
            $parsedQuery = array();
        }

        $this->_scheme  = $scheme;
        $this->_method  = $method;
        $this->_host    = $normalizedHost;
        $this->_port    = $port;
        $this->_path    = isset($parsedUri['path']) ? $parsedUri['path'] : '/';
        $this->_params  = $parsedQuery;
        $this->_headers = $headers;
        $this->_body    = $body;

        foreach ($headers as $key => $value) {
            $key = strtolower($key);
            if ($key == 'content-type' &&
                $value == 'application/x-www-form-urlencoded'
            ) {
                $bodyParams = Utils::parseQuery($this->getBody());
                $this->_params = Utils::mergeParams($bodyParams, $this->getParams());
            } elseif ($key == 'authorization') {
                $this->_consumeAuthHeader(trim($value));
            }
        }
    }

    /**
     * Try to parse an OAuth Authorization header. If successful, set
     * and return the data.
     *
     * @param string $value
     *
     * @return void
     */
    private function _consumeAuthHeader($value)
    {
        if (strpos($value, 'OAuth ') !== 0) {
            return null;
        }
        $value = substr($value, 6);

        $parts = explode(',', $value);
        foreach ($parts as $part) {
            list($partKey, $partValue) = explode('=', trim($part));
            $partValue = rawurldecode(str_replace('"', '', $partValue));
            if ($partKey == 'realm') {
                $this->setRealm($partValue);
            } else {
                $this->_authHeaderParams[$partKey] = $partValue;
            }
        }
    }

    /**
     * Generate the header value to be sent with the HTTP Authorization
     * header for making an OAuth request.
     *
     * @return string
     */
    public function makeOAuthHeader()
    {
        $params = array(
            'realm'                  => $this->getRealm(),
            'oauth_callback'         => $this->getParam('oauth_callback'),
            'oauth_consumer_key'     => $this->getParam('oauth_consumer_key'),
            'oauth_nonce'            => $this->getParam('oauth_nonce'),
            'oauth_signature'        => $this->getSignature(false),
            'oauth_signature_method' => $this->getParam('oauth_signature_method'),
            'oauth_timestamp'        => $this->getParam('oauth_timestamp'),
            'oauth_token'            => $this->getParam('oauth_token'),
            'oauth_version'          => $this->getParam('oauth_version'),
        );

        if (!$params['oauth_callback']) {
            unset($params['oauth_callback']);
        }
        if (!$params['oauth_token']) {
            unset($params['oauth_token']);
        }

        return 'OAuth ' . implode(',', array_map(function($item)
        {
            return $item[0] . '="' . rawurlencode($item[1]) . '"';
        }, array_map(null, array_keys($params), array_values($params))));
    }

    /**
     * Initialize the very bare minimum callbacks that can be set on
     * the signer. The user can provide better ones later on.
     *
     * @see Signer::registerSignatureGenerator()
     * @see Signer::registerTimestampAndNonceValidator()
     * @see Signer::registerConsumerSecretFinder()
     * @see Signer::registerTokenSecretFinder()
     */
    private function _initDefaultCallbacks()
    {
        $this->setTimestampAndNonceValidator(
            function($timestamp, $nonce)
            {
                return true;
            }
        );
        $this->registerSignatureGenerator(
            'hmac-sha1',
            function($signatureBaseString, $consumerSecret, $tokenSecret)
            {
                $key  = rawurlencode($consumerSecret) . '&' .
                    rawurlencode($tokenSecret);
                $hash = hash_hmac('sha1', $signatureBaseString, $key, true);
                return base64_encode($hash);
            }
        );
        $this->registerSignatureGenerator(
            'plaintext',
            function($signatureBaseString, $consumerSecret, $tokenSecret)
            {
                return rawurlencode($consumerSecret) . '&' .
                    rawurlencode($tokenSecret);
            }
        );
        $this->setConsumerSecretFinder(function($consumer)
        {
            return '';
        });
        $this->setTokenSecretFinder(function($consumer, $token)
        {
            return '';
        });
    }

    /**
     * Get the OAuth Base String URI.
     *
     * @return string
     */
    private function _getBaseStringUri()
    {
        $bsu = $this->getScheme() . '://' . strtolower($this->getHost());
        if ($this->getScheme() == 'http' && $this->getPort() != 80 ||
            $this->getScheme() == 'https' && $this->getPort() != 443
        ) {
            $bsu .= ':' . $this->getPort();
        }
        $bsu .= $this->getPath();
        return $bsu;
    }

    /**
     * Get the OAuth parameters, normalized (according to RFC).
     *
     * @return string
     */
    private function _getNormalizedRequestParams()
    {
        $params       = array();
        $mergedParams = Utils::mergeParams(
            $this->getParams(),
            $this->getAuthHeaderParams()
        );
        foreach ($mergedParams as $key => $value) {
            if ($key == 'oauth_signature') {
                continue;
            }
            $params[] = array(rawurlencode($key), rawurlencode($value));
        }

        usort($params, function($a, $b)
        {
            $cmp = strcmp($a[0], $b[0]);
            return $cmp == 0 ? strcmp($a[1], $b[1]) : $cmp;
        });
        $concated = array();
        foreach ($params as $param) {
            $concated[] = $param[0] . '=' . $param[1];
        }

        return implode('&', $concated);
    }

    /**
     * Construct the OAuth signature base string (used for signing the
     * request).
     *
     * @return string
     */
    private function _getSignatureBaseString()
    {
        return strtoupper($this->getMethod()) . '&' .
            rawurlencode($this->_getBaseStringUri()) . '&' .
            rawurlencode($this->_getNormalizedRequestParams());
    }

    /**
     * Get the value of a parameter that was sent in (favor the
     * Authorization header).
     *
     * @param $name
     * @param $default
     *
     * @return mixed|null
     */
    public function getParam($name, $default = null)
    {
        if (isset($this->_authHeaderParams[$name])) {
            return $this->_authHeaderParams[$name];
        } elseif (isset($this->_params[$name])) {
            return $this->_params[$name];
        } else {
            return $default;
        }
    }

    /**
     * Generate a signature for the OAuth request.
     *
     * @param boolean $validateTimestampAndNonce
     *
     * @return mixed
     * @throws ValidationException
     */
    public function getSignature($validateTimestampAndNonce = true)
    {
        if ($validateTimestampAndNonce) {
            $areTimestampAndNonceValid = call_user_func(
                $this->getTimestampAndNonceValidator(),
                $this->getParam('oauth_timestamp'),
                $this->getParam('oauth_nonce')
            );
            if (!$areTimestampAndNonceValid) {
                throw new ValidationException(
                    'Invalid timestamp/nonce combination'
                );
            }
        }

        $consumerSecret = call_user_func(
            $this->getConsumerSecretFinder(),
            $this->getParam('oauth_consumer_key')
        );
        $tokenSecret    = call_user_func(
            $this->getTokenSecretFinder(),
            $this->getParam('oauth_consumer_key'),
            $this->getParam('oauth_token')
        );

        $signatureCallback = $this->getSignatureGenerator(
            $this->getParam('oauth_signature_method', 'hmac-sha1')
        );
        return call_user_func(
            $signatureCallback,
            $this->_getSignatureBaseString(),
            $consumerSecret,
            $tokenSecret
        );
    }

    /**
     * Get the HTTP request body.
     *
     * @return string
     */
    public function getBody()
    {
        return $this->_body;
    }

    /**
     * Get the HTTP request host.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->_host;
    }

    /**
     * Get the HTTP request method (GET, POST, etc).
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * Get the HTTP request params.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * Get the HTTP request path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Get the HTTP request port.
     *
     * @return int
     */
    public function getPort()
    {
        return $this->_port;
    }

    /**
     * Get the HTTP request scheme (http/https).
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->_scheme;
    }

    /**
     * Get the optional OAuth realm (interpretation of this is up to the
     * server).
     *
     * @return string
     */
    public function getRealm()
    {
        return $this->_realm;
    }

    /**
     * Setter for realm.
     *
     * @param $realm
     *
     * @return Signer
     */
    public function setRealm($realm)
    {
        $this->_realm = $realm;
        return $this;
    }

    /**
     * Get the HTTP request headers.
     *
     * @return string
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * Get the params for the OAuth HTTP header.
     *
     * @return array
     */
    public function getAuthHeaderParams()
    {
        return $this->_authHeaderParams;
    }

    /**
     * Get the callback that finds a consumer secret given a consumer.
     *
     * @return callback
     */
    public function getConsumerSecretFinder()
    {
        return $this->_consumerSecretFinder;
    }

    /**
     * Register a callback that finds the secret key for a consumer key.
     *
     * The callback should return a string.
     *
     * @param callback $callback
     *
     * @return Signer
     */
    public function setConsumerSecretFinder($callback)
    {
        $this->_consumerSecretFinder = $callback;
        return $this;
    }

    /**
     * Get the callback that validates the timestamp and nonce
     * combination.
     *
     * @return callback
     */
    public function getTimestampAndNonceValidator()
    {
        return $this->_timestampAndNonceValidator;
    }

    /**
     * Register a callback that checks to see if the timestamp and nonce
     * combination is valid.
     *
     * The callback should return a boolean.
     *
     * @param callback $callback
     *
     * @return Signer
     */
    public function setTimestampAndNonceValidator($callback)
    {
        $this->_timestampAndNonceValidator = $callback;
        return $this;
    }

    /**
     * Get the callback that finds a token secret given a token.
     *
     * @return callback
     */
    public function getTokenSecretFinder()
    {
        return $this->_tokenSecretFinder;
    }

    /**
     * Register a callback that finds the secret key for an OAuth token.
     *
     * The callback should return a string.
     *
     * @param callback $callback
     *
     * @return Signer
     */
    public function setTokenSecretFinder($callback)
    {
        $this->_tokenSecretFinder = $callback;
        return $this;
    }

    /**
     * Get all of the callbacks that generate signatures.
     *
     * @return array
     */
    public function getSignatureGenerators()
    {
        return $this->_signatureGenerators;
    }

    /**
     * Get the signature generator callback for the given signature
     * method.
     *
     * @param $signatureMethod
     *
     * @return mixed
     * @throws SignatureException
     */
    public function getSignatureGenerator($signatureMethod)
    {
        $signatureMethod = strtolower($signatureMethod);
        if (!isset($this->_signatureGenerators[$signatureMethod])) {
            throw new SignatureException(
                'No callback registered for OAuth Signature Method: ' .
                    var_export($signatureMethod, true)
            );
        }
        return $this->_signatureGenerators[$signatureMethod];
    }

    /**
     * Register a callback that knows how to generate an OAuth signature
     * for a certain method.
     *
     * The callback should return a string.
     *
     * @param string   $signatureMethod
     * @param callback $callback
     *
     * @return Signer
     */
    public function registerSignatureGenerator($signatureMethod, $callback)
    {
        $signatureMethod = strtolower($signatureMethod);

        $this->_signatureGenerators[$signatureMethod] = $callback;

        return $this;
    }
}
