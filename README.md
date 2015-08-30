# LogEntries
[![Build Status](https://travis-ci.org/cbschuld/LogEntries.svg?branch=master)](https://travis-ci.org/cbschuld/LogEntries)

A LogEntries specific logging class by [Chris Schuld](http://chrisschuld.com/) for logging information to [LogEntries](https://logentries.com)

## About

LogEntries is an easy-to-use PHP [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md)
compliant logging class used to log information to the [LogEntries](https://logentries.com) SaaS application.

## Installation

### Composer

From the Command Line:

```
composer require cbschuld/LogEntries:1.*
```

In your `composer.json`:

``` json
{
    "require": {
        "cbschuld/LogEntries": "1.*"
    }
}
```

## Basic Usage

``` php
<?php

use cbschuld\LogEntries;

require "vendor/autoload.php";
$token = "2bfbea1e-10c3-4419-bdad-7e6435882e1f"; // your LogEntries token (sample from docs)

$log = LogEntries::getLogger($token,true,true); // create persistent SSL-based connection
$log->info("some information");
$log->notice(json_encode(["status"=>"ok","message"=>"send some json"]));

```

## Advanced Usage

You can send all of the logging functions either a string (PSR-3), encoded JSON (PSR-3)
or an array which will be encoded into JSON (not PSR-3 but available)

``` php
<?php

use cbschuld\LogEntries;

require "vendor/autoload.php";
$token = "2bfbea1e-10c3-4419-bdad-7e6435882e1f"; // your LogEntries token (sample from docs)
$jsonInfo = ["json"=>true,"example"=>"yes","works"=>true];

$log = LogEntries::getLogger($token,true,true); // create persistent SSL-based connection
$log->info(["status"=>"ok","example"=>"with json messages"]);
$log->notice($jsonInfo);

```


## PSR-3 Compliant

LogEntries is PHP [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md)
compliant. This means it implements the `Psr\Log\LoggerInterface`.

[See Here for the interface definition.](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#3-psrlogloggerinterface)


## License

The MIT License

Copyright (c) 2015 Chris Schuld <chris@chrisschuld.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.