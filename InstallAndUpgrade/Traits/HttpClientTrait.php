<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Message\ResponseInterface;
use Tuna\CloudflareMiddleware;

trait HttpClientTrait {
    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var CookieJar
     */
    protected $cookieJar;

    protected function httpClient() {
        if(!$this->httpClient) {
            $this->httpClient = \XF::app()->http()->client();
            $this->httpClient->getConfig('handler')->push(CloudflareMiddleware::create());
        }

        return $this->httpClient;
    }

    protected function cookieJar() {
        if(!$this->cookieJar) {
            $this->cookieJar = new CookieJar();
        }

        return $this->cookieJar;
    }

    protected function httpRequest($url, $options = [], $method = 'get', $cookies = true, $throwErrors = true) {
        if($cookies) {
            $options['cookies'] = $this->cookieJar();
        }

        $addonCache = \XF::app()->container('addon.cache');
        $options['headers'] = array_merge(isset($options['headers']) ? $options['headers'] : [], [
            'User-Agent' => 'th-install-upgrade/' . $addonCache['ThemeHouse/InstallAndUpgrade'] .
                ' (PHP ' . phpversion() . ')',
            'Accept-Charset' => 'utf-8',
        ]);

        /** @var ResponseInterface $response */
        $response = $this->httpClient()->{$method}($url, $options);

        if($throwErrors && $response->getStatusCode() != 200) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->exception(\XF::phrase('th_iau_response_error', [
                'statusCode' => $response->getStatusCode(),
                'reason' => $response->getReasonPhrase()
            ]));
        }

        return $response;
    }
}