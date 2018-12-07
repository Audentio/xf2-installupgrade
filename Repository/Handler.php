<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use ThemeHouse\InstallAndUpgrade\Entity\Language;
use ThemeHouse\InstallAndUpgrade\Entity\Style;
use XF\Mvc\Entity\ArrayCollection;
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

        if (!$handler)
        {
            return null;
        }

        if (!isset($this->handlers[$handler]))
        {
			$this->handlers[$handler] = $this->getProviderHandler('iau_provider_' . $handler);
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
        if (!isset($this->handlers[$name]))
        {
			$this->handlers[$name] = $this->getProviderHandler('iau_provider_' . $name);
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
				if (strpos($urlComponents['path'], 'resources/') !== false) {
					return 'xf2rm';
				}
                return null;

            default:
                return $this->db()->query('SELECT provider_id FROM xf_th_installupgrade_profile WHERE INSTR(base_url, ?)', [$url])->fetchColumn(0);
        }
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

        if(strpos($addOnId, 'ThemeHouse/') === 0 && isset($data->extra['product_id'])) {
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
	
	/**
	 * @param bool $arrayCollection
	 *
	 * @return \ThemeHouse\InstallAndUpgrade\Provider\AbstractHandler[]|ArrayCollection
	 * @throws \Exception
	 */
	public function getProviderHandlers($arrayCollection = false)
	{
		$handlers = [];
		
		foreach (\XF::app()->getContentTypeField('th_installupgrade_provider_handler_class') AS $contentType => $handlerClass)
		{
			if (class_exists($handlerClass))
			{
				$handlerClass = \XF::extendClass($handlerClass);
				$handlers[$contentType] = new $handlerClass($contentType);
			}
		}
		
		return $arrayCollection ? new ArrayCollection($handlers) : $handlers;
	}
	
	/**
	 * @param string $type
	 *
	 * @return \ThemeHouse\InstallAndUpgrade\Provider\AbstractHandler|null
	 * @throws \Exception
	 */
	public function getProviderHandler($type)
	{
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'th_installupgrade_provider_handler_class');
		if (!$handlerClass)
		{
			return null;
		}
		
		if (!class_exists($handlerClass))
		{
			return null;
		}
		
		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}
}