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

    /**
     * Ensure that query parsing works as expected (create lists of
     * values for duplicate parameters instead of overriding).
     */
    public function testParseQuery()
    {
        $query = 'foo=bar&foo=baz&foo&bar=foo';
        $expected = array(
            'foo' => array(
                'bar',
                'baz',
                '',
            ),
            'bar' => 'foo',
        );
        $actual = Utils::parseQuery($query);
        $this->assertEquals($expected, $actual);

        $query = 'foo%20foo%5B%5D=42';
        $expected = array(
            'foo foo[]' => 42,
        );
        $actual = Utils::parseQuery($query);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that complex param array merging scenarios all work
     * correctly.
     */
    public function testMergeParams()
    {
        $a = array(1, 2, 3);
        $b = array(4, 5, 6);
        $c = 'other';
        $expected = array(1, 2, 3, 4, 5, 6, 'other');
        $actual = Utils::mergeParams($a, $b, $c);
        $this->assertEquals($expected, $actual);

        $a = array('bar' => 'foo');
        $b = array('foo' => 'bar');
        $expected = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );
        $actual = Utils::mergeParams($a, $b);
        $this->assertEquals($expected, $actual);

        $a = array('foo' => 'bar', 'bar' => 'foo');
        $b = array('foo' => 'bar');
        $expected = array(
            'foo' => array('bar', 'bar'),
            'bar' => 'foo',
        );
        $actual = Utils::mergeParams($a, $b);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure that parsing header values works as expected for a
     * multitude of edge cases.
     */
    public function testParseHeader()
    {
        $input = 'foo=bar,bar=foo';
        $expected = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = 'foo=bar, foo=baz, bar=foo';
        $expected = array(
            'bar' => 'foo',
            'foo' => array('bar', 'baz'),
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = 'foo=bar,  bar=foo';
        $expected = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = 'foo=bar     ,  bar=foo';
        $expected = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = 'foo="bar",bar="foo"';
        $expected = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = 'foo="b,ar",bar="foo"';
        $expected = array(
            'foo' => 'b,ar',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = '"foo"="bar","bar"="foo"';
        $expected = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = sprintf('"foo\"%s%s"="bar","bar"="foo"', chr(92), chr(92));
        $expected = array(
            'foo"\\' => 'bar',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = sprintf('"foo"="bar\"%s%s","bar"="foo"', chr(92), chr(92));
        $expected = array(
            'foo' => 'bar"\\',
            'bar' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = sprintf('"foo"="bar","bar\"%s%s"="foo"', chr(92), chr(92));
        $expected = array(
            'foo' => 'bar',
            'bar"\\' => 'foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = sprintf('"foo"="bar","bar"="\"%s%sfoo"', chr(92), chr(92));
        $expected = array(
            'foo' => 'bar',
            'bar' => '"\\foo',
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);

        $input = 'foo="bar",foo="more",foo="extra"';
        $expected = array(
            'foo' => array('bar', 'more', 'extra'),
        );
        $actual = Utils::parseHeader($input);
        $this->assertEquals($expected, $actual);
    }
}
