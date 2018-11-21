<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use ThemeHouse\InstallAndUpgrade\Entity\Language;
use ThemeHouse\InstallAndUpgrade\Entity\Provider;
use ThemeHouse\InstallAndUpgrade\Entity\Style;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;

class Handler extends Repository
{
    protected $handlers;

    /**
     * ProfileHandler constructor.
     * @param Manager $em
     * @param $identifier
     * @throws \Exception
     */
    public function __construct(Manager $em, $identifier)
    {
        parent::__construct($em, $identifier);

        $class = \XF::extendClass('ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Upload');
        $this->handlers['upload'] = new $class();
    }

    /**
     * @param $contentType
     * @param $contentId
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function getHandler($contentType, $contentId, $data = null)
    {
        $handler = false;

        switch ($contentType) {
            case 'addOn':
                $handler = $this->getHandlerForAddOn($contentId, $data);
                break;

            case 'style':
                $handler = $this->getHandlerForStyle($contentId, $data);
                break;

            case 'language':
                $handler = $this->getHandlerForLanguage($contentId, $data);
                break;

            case 'url':
                $handler = $this->getHandlerForUrl($contentId);
        }

        if (!$handler) {
            return null;
        }

        if (!isset($this->handlers[$handler])) {
            $this->initHandler($handler);
        }

        return $this->handlers[$handler];
    }

    /**
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function getHandlerByName($name)
    {
        if (!isset($this->handlers[$name])) {
            $this->initHandler($name);
        }

        return $this->handlers[$name];
    }

    /**
     * @param $url
     * @return null|string
     */
    public function getHandlerForUrl($url)
    {
        $urlComponents = parse_url($url);

        switch ($urlComponents['host']) {
            case 'themehouse.com':
                return 'themehouse';

            case 'xenforo.com':
                if (strpos($urlComponents['path'], 'customers') !== false) {
                    return 'xenforo';
                } else {
                    if (strpos($urlComponents['path'], 'resources/') !== false) {
                        return 'xf2rm';
                    }
                }
                return null;

            default:
                return $this->db()->query('SELECT provider_id FROM xf_th_installupgrade_profile WHERE INSTR(?, base_url)', [$url])->fetchColumn(0);
        }
    }

    /**
     * @param $handler
     * @throws \Exception
     */
    protected function initHandler($handler)
    {
        /** @var Provider $provider */
        $provider = $this->em->find('ThemeHouse\InstallAndUpgrade:Provider', $handler);
        $this->handlers[$handler] = $provider->getHandler();
    }

    /**
     * @param $addOnId
     * @param AddOn|null $data
     * @return null|string
     */
    protected function getHandlerForAddOn($addOnId, AddOn $data = null)
    {
        if (!$data) {
            $data = $this->em->find('ThemeHouse\InstallAndUpgrade:AddOn', $addOnId);
        }

        if(in_array($addOnId, ['XFRM', 'XFI', 'XFMG', 'XFES'])) {
            return 'xenforo';
        }

        if(strpos($addOnId, 'ThemeHouse/') == 0 && isset($data->extra['product_id'])) {
            return 'themehouse';
        }

        if ($data && $data->download_url) {
            return $this->getHandlerForUrl($data->download_url);
        }

        return null;
    }

    /**
     * @param $styleId
     * @param Style|null $data
     * @return null|string
     */
    protected function getHandlerForStyle($styleId, Style $data = null)
    {
        if (!$data) {
            $data = $this->em->find('ThemeHouse\InstallAndUpgrade:Style', $styleId);
        }

        if ($data && $data->download_url) {
            return $this->getHandlerForUrl($data->download_url);
        }

        return null;
    }

    /**
     * @param $languageId
     * @param Language|null $data
     * @return null|string
     */
    protected function getHandlerForLanguage($languageId, Language $data = null)
    {
        if (!$data) {
            $data = $this->em->find('ThemeHouse\InstallAndUpgrade:Language', $languageId);
        }


        if ($data && $data->download_url) {
            return $this->getHandlerForUrl($data->download_url);
        }
    }
}