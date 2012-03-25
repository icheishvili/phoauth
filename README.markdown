PhoAuth
=======

[![Build Status](https://secure.travis-ci.org/icheishvili/phoauth.png?branch=master)](http://travis-ci.org/icheishvili/phoauth)

OAuth sucks. The goal of this library is to make it stop sucking. It's
for PHP 5.3+. It is licensed under the
[New BSD License](http://www.opensource.org/licenses/bsd-license.php).

Some libraries...

  - Force the use of HTTP in a rigidly prescribed way
  - Suppress errors, making debugging impossible
  - Have weird names for parameters
  - Are coupled to a single web server with an arc welder
  - Only work as a client OR a server
  - Are written by people who get paid by # of lines of code
  - Work by funnelling gerbils through pneumatic tubes

Check this out:

```php
<?php
use PhoAuth\Utils;

$signer = new PhoAuth\Signer(
    Utils::getServerScheme(),
    Utils::getServerMethod(),
    Utils::getServerHost(),
    Utils::getServerPort(),
    Utils::getServerRequestUri(),
    Utils::getServerHeaders(),
    Utils::getServerInputBody());

echo $signer->getSignature();
```

The first thing to notice is that all of the HTTP information that
PhoAuth needs is passed in. CS 101 has these concepts about
encapsulation and separation of concerns--this is an example of that.

The second thing to notice is how easily Utils lets most people work
with most PHP set ups. At the same time, due to PhoAuth's design not
sucking, it be used with [Hurricane](http://gethurricane.org/),
[Guzzle](http://guzzlephp.org/), or whatever.

While we're at it, here's how to write a
[server](https://github.com/icheishvili/phoauth/blob/master/examples/index.php)
and a
[client](https://github.com/icheishvili/phoauth/blob/master/examples/client.php).
Notice the use of custom callbacks to control how data flow and
validation works. Also notice how nothing is coupled to the client's
actual behavior.

A few other niceties:

  - The implementation is clean and easy to read
  - The code is minimal and cohesive, leaving room to build
    use-case-specific abstractions
  - Exceptions are specific and detailed
  - Everything is covered by unit tests
