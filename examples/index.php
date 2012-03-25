<?php

require __DIR__ . '/../autoload.php.dist';
use PhoAuth\Utils, PhoAuth\Signer;

/**
 * A quick abstraction for storing data as JSON. Note: will not scale :)
 */
class DataFile
{
    /**
     * The name of the data file.
     *
     * @var string
     */
    const DATA_FILE = 'data.json';

    /**
     * Get the path to the file.
     *
     * @static
     * @return string
     */
    public static function getPath()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . self::DATA_FILE;
    }

    /**
     * Read the file, decode the JSON, and return the results.
     *
     * @static
     * @return mixed
     */
    public static function read()
    {
        return json_decode(file_get_contents(self::getPath()));
    }

    /**
     * Encode the given data as JSON and write it to the file.
     *
     * @static
     *
     * @param $data
     *
     * @return void
     */
    public static function write($data)
    {
        file_put_contents(self::getPath(), json_encode($data));
    }
}

$signer = new Signer(
    Utils::getServerScheme(),
    Utils::getServerMethod(),
    Utils::getServerHost(),
    Utils::getServerPort(),
    Utils::getServerRequestUri(),
    Utils::getServerHeaders(),
    Utils::getServerInputBody()
);
$signer->setTimestampAndNonceValidator(function($timestamp, $nonce)
{
    if ($timestamp < time() - 500 || $timestamp > time() + 500) {
        return false;
    }
    $data = DataFile::read();
    foreach ($data->nonces as $combo) {
        if ($combo->value == $nonce && $timestamp == $combo->timestamp) {
            return false;
        }
    }
    $data->nonces[] = array(
        'value'     => $nonce,
        'timestamp' => (int)$timestamp,
    );
    DataFile::write($data);

    return true;
});
$signer->setConsumerSecretFinder(function($consumer)
{
    $data = DataFile::read();
    foreach ($data->consumers as $potential) {
        if ($potential->key == $consumer) {
            return $potential->secret;
        }
    }
    throw new \Exception(
        'Could not find consumer for key: ' . var_export($consumer, true)
    );
});

$normalizedPath = preg_replace('#/+$#', '', $signer->getPath());
if ($normalizedPath == '/request-token') {
    $signer->setTokenSecretFinder(function($consumer, $token)
    {
        return '';
    });
} else {
    $signer->setTokenSecretFinder(function($consumer, $token)
    {
        $data = DataFile::read();
        foreach ($data->tokens as $potential) {
            if ($potential->consumer == $consumer &&
                $potential->key == $token
            ) {
                return $potential->secret;
            }
        }
        throw new \Exception(
            'Could not find token for consumer: ' .
                var_export($consumer, true) .
                ' and key: ' .
                var_export($token, true)
        );
    });
}

if ($signer->getSignature() != $signer->getParam('oauth_signature')) {
    echo '<h1>OAuth signatures do not match</h1>';
    echo '<b>Expected: </b>' . $signer->getSignature(false);
    echo '<br/>';
    echo '<b>Actual: </b>' . $signer->getParam('oauth_signature');
    exit;
}

if ($normalizedPath == '/request-token') {
    $newToken       = sha1(uniqid(microtime()));
    $newTokenSecret = sha1(uniqid(microtime()));
    $data           = DataFile::read();
    $data->tokens[] = array(
        'type'     => 'request',
        'key'      => $newToken,
        'secret'   => $newTokenSecret,
        'consumer' => $signer->getParam('oauth_consumer_key'),
    );
    DataFile::write($data);
    echo http_build_query(
        array(
            'oauth_token'              => $newToken,
            'oauth_token_secret'       => $newTokenSecret,
            'oauth_callback_confirmed' => 1,
        )
    );
} elseif ($normalizedPath == '/direct-access-token') {
    $data = DataFile::read();

    $validUserFound = false;
    foreach ($data->users as $user) {
        if ($user->username == $signer->getParam('username') &&
            $user->password == $signer->getParam('password')
        ) {
            $validUserFound = true;
            break;
        }
    }
    if (!$validUserFound) {
        echo '<h1>Invalid username or password</h1>';
        exit;
    }

    $newToken       = sha1(uniqid(microtime()));
    $newTokenSecret = sha1(uniqid(microtime()));
    $data           = DataFile::read();
    $data->tokens[] = array(
        'type'     => 'access',
        'key'      => $newToken,
        'secret'   => $newTokenSecret,
        'consumer' => $signer->getParam('oauth_consumer_key'),
    );
    DataFile::write($data);
    echo http_build_query(
        array(
            'oauth_token'              => $newToken,
            'oauth_token_secret'       => $newTokenSecret,
        )
    );
} else {
    echo '<h1>Success! You are seeing this because you made it through ' .
        'the OAuth gauntlet.</h1>';
}
