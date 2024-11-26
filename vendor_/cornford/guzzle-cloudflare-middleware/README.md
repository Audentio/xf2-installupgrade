# A Guzzle Cloudflare bypass middleware.

[![Latest Stable Version](https://poser.pugx.org/cornford/guzzle-cloudflare-middleware/version.png)](https://packagist.org/packages/cornford/guzzle-cloudflare-middleware)
[![Total Downloads](https://poser.pugx.org/cornford/guzzle-cloudflare-middleware/d/total.png)](https://packagist.org/packages/cornford/guzzle-cloudflare-middleware)
[![Build Status](https://travis-ci.org/bradcornford/guzzle-cloudflare-middleware.svg?branch=master)](https://travis-ci.org/bradcornford/guzzle-cloudflare-middleware)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bradcornford/guzzle-cloudflare-middleware/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/bradcornford/guzzle-cloudflare-middleware/?branch=master)

## Installation

Begin by installing this package through Composer. Edit your project's `composer.json` file to require `cornford/guzzle-cloudflare-middleware`.

    "require": {
        "cornford/guzzle-cloudflare-middleware": "1.*"
    }

Finally, update Composer from the Terminal:

    composer update

## Usage

It's really as simple as using the middleware class with Guzzle:

``` php
use GuzzleHttp\Client;
use Cornford\GuzzleCloudflareMiddleware\CloudflareMiddleware;
use GuzzleHttp\Cookie\FileCookieJar;

$client = new Client(['cookies' => new FileCookieJar('cookies.txt')]);

$client->getConfig('handler')->push(CloudflareMiddleware::create());

$res = $client->request('GET', 'http://www.exemple.com/');
echo $res->getBody();
```

### License

guzzle-cloudflare-middleware is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
