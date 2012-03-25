<?php namespace PhoAuth\Tests;

use PhoAuth\Utils;
use \PHPUnit_Framework_TestCase;

/**
 * Runs unit tests on the utils class.
 */
class UtilsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Override the $_SERVER superglobal to known values.
     */
    public function setUp()
    {
        $_SERVER = array(
            'DOCUMENT_ROOT'        => '/tmp',
            'REMOTE_ADDR'          => '127.0.0.1',
            'REMOTE_PORT'          => '59361',
            'SERVER_SOFTWARE'      => 'PHP 5.4.0 Development Server',
            'SERVER_PROTOCOL'      => 'HTTP/1.1',
            'SERVER_NAME'          => '0.0.0.0',
            'SERVER_PORT'          => '8000',
            'REQUEST_URI'          => '/',
            'REQUEST_METHOD'       => 'GET',
            'SCRIPT_NAME'          => '/index.php',
            'SCRIPT_FILENAME'      => '/tmp/index.php',
            'PHP_SELF'             => '/index.php',
            'HTTP_HOST'            => 'localhost:8000',
            'HTTP_CONNECTION'      => 'keep-alive',
            'HTTP_CACHE_CONTROL'   => 'max-age=0',
            'HTTP_USER_AGENT'      => 'Mosaic/1.0',
            'HTTP_ACCEPT'          => 'text/html',
            'HTTP_ACCEPT_ENCODING' => 'gzip,deflate,sdch',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'HTTP_ACCEPT_CHARSET'  => 'utf-8',
            'REQUEST_TIME_FLOAT'   => 1332648053.3698,
            'REQUEST_TIME'         => 1332648053,
        );
    }

    /**
     * Ensure that parsing headers works as expected.
     */
    public function testGetServerHeaders()
    {
        $expected = array(
            'Host'            => 'localhost:8000',
            'Connection'      => 'keep-alive',
            'Cache-Control'   => 'max-age=0',
            'User-Agent'      => 'Mosaic/1.0',
            'Accept'          => 'text/html',
            'Accept-Encoding' => 'gzip,deflate,sdch',
            'Accept-Language' => 'en-US,en;q=0.8',
            'Accept-Charset'  => 'utf-8',
        );
        $actual   = Utils::getServerHeaders();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that the referenced function works.
     */
    public function testGetServerScheme()
    {
        $expected = 'http';
        $actual   = Utils::getServerScheme();
        $this->assertEquals($expected, $actual);

        $_SERVER['HTTPS'] = 'on';
        $expected = 'https';
        $actual   = Utils::getServerScheme();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that the referenced function works.
     */
    public function testGetServerHost()
    {
        $expected = 'localhost:8000';
        $actual = Utils::getServerHost();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that the referenced function works.
     */
    public function testGetServerPort()
    {
        $expected = 8000;
        $actual = Utils::getServerPort();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that the referenced function works.
     */
    public function testGetServerMethod()
    {
        $expected = 'GET';
        $actual = Utils::getServerMethod();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that the referenced function works.
     */
    public function testGetServerRequestUri()
    {
        $expected = '/';
        $actual = Utils::getServerRequestUri();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that the referenced function works.
     */
    public function testGetServerInputBody()
    {
        $expected = '';
        $actual = Utils::getServerInputBody();
        $this->assertEquals($expected, $actual);
    }
}
