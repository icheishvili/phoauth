<?php

require __DIR__ . '/../autoload.php.dist';
use PhoAuth\Signer;

$pathToZendFramework = '/Users/icheishvili/Downloads/ZendFramework-1.11.11-minimal/library';
set_include_path(get_include_path() . PATH_SEPARATOR . $pathToZendFramework);
require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance();

//=============================================================================
// PART 1
//=============================================================================
$scheme = 'http';
$method = 'GET';
$host   = '127.0.0.1';
$port   = 8000;
$uri    = '/request-token';
$params = array(
    'oauth_callback'         => 'oob',
    'oauth_consumer_key'     => '92429d82a41e930486c6de5ebda9602d55c39986',
    'oauth_nonce'            => uniqid(),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp'        => time(),
    'oauth_version'          => '1.0',
);
$signer = new Signer(
    $scheme, $method, $host, $port, $uri . '?' . http_build_query($params)
);
$signer->setConsumerSecretFinder(function($consumer)
{
    return 'e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98';
});

$client = new Zend_Http_Client();
$client->setUri($scheme . '://' . $host . ':' . $port . $uri);
$client->setMethod($method);
$client->setHeaders('Authorization', $signer->makeOAuthHeader());
$response = $client->request();
$body     = $response->getBody();
echo 'request token: ' . $body . PHP_EOL;
parse_str($body, $requestToken);

//=============================================================================
// PART 2
//=============================================================================

$scheme  = 'http';
$method  = 'POST';
$host    = '127.0.0.1';
$port    = 8000;
$uri     = '/direct-access-token';
$headers = array(
    'Content-Type' => 'application/x-www-form-urlencoded'
);
$body    = http_build_query(array(
    'username' => 'user1',
    'password' => 'password1',
));
$params  = array(
    'oauth_consumer_key'     => '92429d82a41e930486c6de5ebda9602d55c39986',
    'oauth_nonce'            => uniqid(),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp'        => time(),
    'oauth_token'            => $requestToken['oauth_token'],
    'oauth_version'          => '1.0',
);
$signer  = new Signer(
    $scheme, $method, $host, $port,
    $uri . '?' . http_build_query($params),
    $headers, $body
);
$signer->setConsumerSecretFinder(function($consumer)
{
    return 'e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98';
});
$signer->setTokenSecretFinder(function($token) use ($requestToken)
{
    return $requestToken['oauth_token_secret'];
});

$client = new Zend_Http_Client();
$client->setUri($scheme . '://' . $host . ':' . $port . $uri);
$client->setMethod($method);
$client->setHeaders('Authorization', $signer->makeOAuthHeader());
$client->setHeaders($headers);
$client->setRawData($body);
$response = $client->request();
$body     = $response->getBody();
echo 'access token: ' . $body . PHP_EOL;
parse_str($body, $accessToken);

//=============================================================================
// PART 3
//=============================================================================
$scheme = 'http';
$method = 'GET';
$host   = '127.0.0.1';
$port   = 8000;
$uri    = '/get-some-data';
$params = array(
    'oauth_consumer_key'     => '92429d82a41e930486c6de5ebda9602d55c39986',
    'oauth_nonce'            => uniqid(),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp'        => time(),
    'oauth_token'            => $accessToken['oauth_token'],
    'oauth_version'          => '1.0',
);
$signer = new Signer(
    $scheme, $method, $host, $port, $uri . '?' . http_build_query($params)
);
$signer->setConsumerSecretFinder(function($consumer)
{
    return 'e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98';
});
$signer->setTokenSecretFinder(function($token) use ($accessToken)
{
    return $accessToken['oauth_token_secret'];
});

$client = new Zend_Http_Client();
$client->setUri($scheme . '://' . $host . ':' . $port . $uri);
$client->setMethod($method);
$client->setHeaders('Authorization', $signer->makeOAuthHeader());
$response = $client->request();
$body     = $response->getBody();
echo 'final response: ' . $body . PHP_EOL;