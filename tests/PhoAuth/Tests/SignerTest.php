<?php namespace PhoAuth\Tests;

use PhoAuth\Signer;
use \PHPUnit_Framework_TestCase;

/**
 * Runs unit tests on the signer class.
 */
class SignerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Ensure that a missing signature method causes an exception to be
     * thrown.
     *
     * @expectedException PhoAuth\SignatureException
     */
    public function testNoSignatureMethod()
    {
        $signer = new Signer(
            'http', 'GET', 'localhost', '80',
            '/?oauth_signature_method=magik'
        );
        $signer->getSignature();
    }

    /**
     * Ensure that invalid timestamp/nonce combinations throw the right
     * exception.
     *
     * @expectedException PhoAuth\ValidationException
     */
    public function testInvalidTimestampNonce()
    {
        $signer = new Signer(
            'http', 'GET', 'localhost', '80', '/'
        );
        $signer->setTimestampAndNonceValidator(function()
        {
            return false;
        });
        $signer->getSignature();
    }

    /**
     * Test that the parsing of the OAuth header works.
     */
    public function testOAuthHeader()
    {
        $authorization = 'OAuth realm="test",' .
            'oauth_callback="oob",' .
            'oauth_consumer_key="b",' .
            'oauth_nonce="2W54W9",' .
            'oauth_timestamp="1332630750",' .
            'oauth_version="1.0",' .
            'oauth_signature_method="HMAC-SHA1",' .
            'oauth_signature="UxOtOUQLMr%2Fk%2B9DckYfKG4Y4xvM%3D"';

        $signer = new Signer(
            'http', 'GET', 'localhost', '80', '/',
            array('Authorization' => $authorization)
        );

        $expectedRealm = 'test';
        $actualRealm   = $signer->getRealm();
        $this->assertEquals($expectedRealm, $actualRealm);

        $expectedParams = array(
            'oauth_callback'         => 'oob',
            'oauth_consumer_key'     => 'b',
            'oauth_nonce'            => '2W54W9',
            'oauth_timestamp'        => '1332630750',
            'oauth_version'          => '1.0',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_signature'        => 'UxOtOUQLMr/k+9DckYfKG4Y4xvM=',
        );
        $actualParams   = $signer->getAuthHeaderParams();
        $this->assertEquals($expectedParams, $actualParams);
    }

    /**
     * Test that the interaction between the Content-Type header and
     * interpreting params in the body works.
     */
    public function testNoBody()
    {

        $signer = new Signer(
            'http', 'GET', 'localhost', '80', '/', array(), 'a=b&b=c'
        );

        $expectedParams = array();
        $actualParams   = $signer->getParams();
        $this->assertEquals($expectedParams, $actualParams);
    }

    /**
     * Test that the interaction between the Content-Type header and
     * interpreting params in the body works. Also ensure that GET parameters
     * have precedence in the presence of conflicts.
     */
    public function testBody()
    {
        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        $signer  = new Signer(
            'http', 'GET', 'localhost', '80', '/?a=f', $headers, 'a=b&b=c'
        );

        $expectedParams = array(
            'a' => array('b', 'f'),
            'b' => 'c',
        );
        $actualParams   = $signer->getParams();
        $this->assertEquals($expectedParams, $actualParams);
    }

    /**
     * Test that a content-type header with a charset in it also works.
     */
    public function testBodyWithSpecifiedCharset()
    {
        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
        );
        $signer  = new Signer(
            'http', 'GET', 'localhost', '80', '/?a=f', $headers, 'a=b&b=c'
        );

        $expectedParams = array(
            'a' => array('b', 'f'),
            'b' => 'c',
        );
        $actualParams   = $signer->getParams();
        $this->assertEquals($expectedParams, $actualParams);
    }

    /**
     * Ensure that generating an OAuth signature works as expected.
     */
    public function testSignature()
    {
        $authorization = 'OAuth realm="",' .
            'oauth_consumer_key="",' .
            'oauth_signature_method="HMAC-SHA1",' .
            'oauth_timestamp="1332631878",' .
            'oauth_nonce="6e2b4b1f9824d919071106c01964eb15",' .
            'oauth_version="1.0",' .
            'oauth_signature="clol1a02imIYi8hzZVll4HjWIYs%3D"';

        $headers = array(
            'Authorization' => $authorization,
        );
        $signer  = new Signer(
            'http', 'GET', 'localhost', '80', '/', $headers
        );

        $expected = 'clol1a02imIYi8hzZVll4HjWIYs=';
        $actual   = $signer->getSignature();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that generating an OAuth HTTP Authorization header works.
     * This is the version for the request token, which does not include
     * the oauth_token parameter.
     */
    public function testMakeRequestHeader()
    {
        $params = array(
            'oauth_version'          => '1.0',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => 1234567890,
            'oauth_nonce'            => 'asdf1234',
            'oauth_consumer_key'     => 'consumerasdf',
            'oauth_callback'         => 'http://example.net/',
        );
        $signer = new Signer(
            'http', 'GET', 'localhost', '80', '/?' . http_build_query($params)
        );
        $signer->setRealm('admins');

        $expected = 'OAuth realm="admins",' .
            'oauth_callback="http%3A%2F%2Fexample.net%2F",' .
            'oauth_consumer_key="consumerasdf",' .
            'oauth_nonce="asdf1234",' .
            'oauth_signature="jxhGaw6dKp7PMHuy1P0TbyvfWqI%3D",' .
            'oauth_signature_method="HMAC-SHA1",' .
            'oauth_timestamp="1234567890",' .
            'oauth_version="1.0"';
        $actual   = $signer->makeOAuthHeader();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that generating an OAuth HTTP Authorization header works.
     * This is the version for the non-request token, which does includes
     * the oauth_token parameter.
     */
    public function testMakeAccessHeader()
    {
        $params = array(
            'oauth_version'          => '1.0',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => 1234567890,
            'oauth_nonce'            => 'asdf1234',
            'oauth_consumer_key'     => 'consumerasdf',
            'oauth_callback'         => 'http://example.net/',
            'oauth_token'            => 'tokenasdf',
        );
        $signer = new Signer(
            'http', 'GET', 'localhost', '80', '/?' . http_build_query($params)
        );
        $signer->setRealm('admins');

        $expected = 'OAuth realm="admins",' .
            'oauth_callback="http%3A%2F%2Fexample.net%2F",' .
            'oauth_consumer_key="consumerasdf",' .
            'oauth_nonce="asdf1234",' .
            'oauth_signature="6xtmCsWZCcSky02%2BScTH7M%2FQvqw%3D",' .
            'oauth_signature_method="HMAC-SHA1",' .
            'oauth_timestamp="1234567890",' .
            'oauth_token="tokenasdf",' .
            'oauth_version="1.0"';
        $actual   = $signer->makeOAuthHeader();
        $this->assertEquals($expected, $actual);
    }
}
