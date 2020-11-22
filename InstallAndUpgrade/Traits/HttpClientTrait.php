<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use Tuna\CloudflareMiddleware;

/**
 * Trait HttpClientTrait
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits
 */
trait HttpClientTrait
{
    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var CookieJar
     */
    protected $cookieJar;

    /**
     * @param $url
     * @param array $options
     * @param string $method
     * @param bool $cookies
     * @param bool $throwErrors
     * @return mixed
     */
    protected function httpRequest($url, $options = [], $method = 'get', $cookies = true, $throwErrors = true)
    {
        if ($cookies) {
            $options['cookies'] = $this->cookieJar();
        }

        $addonCache = \XF::app()->container('addon.cache');
        $options['headers'] = array_merge(isset($options['headers']) ? $options['headers'] : [], [
            'User-Agent' => 'th-install-upgrade/' . $addonCache['ThemeHouse/InstallAndUpgrade'] .
                ' (PHP ' . phpversion() . ')',
            'Accept-Charset' => 'utf-8',
        ]);

        $response = null;

        try
        {
            /** @var Response $response */
            $response = $this->httpClient()->{$method}($url, $options);
        }
        catch (\GuzzleHttp\Exception\RequestException $e)
        {
            if ($throwErrors)
            {
                $this->exception($e->getMessage());
            }
        }

        if ($throwErrors && $response->getStatusCode() != 200) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->exception(\XF::phrase('th_iau_response_error', [
                'statusCode' => $response->getStatusCode(),
                'reason' => $response->getReasonPhrase()
            ]));
        }

        return $response;
    }

    /**
     * @return CookieJar
     */
    protected function cookieJar()
    {
        if (!$this->cookieJar) {
            $this->cookieJar = new CookieJar();
        }

        return $this->cookieJar;
    }

    /**
     * @return Client
     */
    protected function httpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = \XF::app()->http()->client();
            $this->httpClient->getConfig('handler')->push(CloudflareMiddleware::create());
        }

        return $this->httpClient;
    }
}