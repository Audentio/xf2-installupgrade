<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

class AddOn extends XFCP_AddOn
{
    /**
     * @return View
     */
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof View) {
            $addOns = $response->getParam('installed');

            $entity = $this->finder('ThemeHouse\InstallAndUpgrade:AddOn')
                ->where('update_available', '=', 1)
                ->where('addon_id', array_keys($addOns))->fetchOne();

            $updates = (bool)$entity;

            $response->setParam('th_iau_updates_available', $updates);
        }

        return $response;
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionSyncChanges(ParameterBag $params)
    {
        $this->assertValidCsrfToken($this->filter('t', 'str'));

        $addOn = $this->assertAddOnAvailable($params->addon_id_url);
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $entity */
        $entity = $addOn->getInstalledAddOn();
        if ($entity->isInstallUpgradeJsonHashChanged() && \XF::$debugMode) {
            $entity->importInstallUpgradeJSON();

            if ($addOn->hasPendingChanges()) {
                return parent::actionSyncChanges($params);
            }

            return $this->redirect($this->buildLink('add-ons'));
        }

        return parent::actionSyncChanges($params);
    }

    /**
     * @param ParameterBag $params
     * @return View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionUpgrade(ParameterBag $params)
    {
        $response = parent::actionUpgrade($params);

        if($response instanceof Redirect) {
            $id = $this->getAddOnRepo()->convertAddOnIdUrlVersionToBase($params->addon_id_url);
            /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $addOn */
            $addOn = $this->em()->find('XF:AddOn', $id);
            $data = $addOn->THInstallUpgradeData;

            if($data) {
                /** @var \ThemeHouse\InstallAndUpgrade\Entity\AddOn $data */
                $data->getHandler()->checkAddOnForUpdates($data);
            }
        }

        return $response;
    }

    /**
     * @param ParameterBag $params
     * @return View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    public function actionInstall(ParameterBag $params) {

        $product = $this->filter([
            'profile_id' => 'uint',
            'product_id' => 'str'
        ]);

        $response = parent::actionInstall($params);

        if($this->isPost()) {
            $addOn = $this->assertAddOnAvailable($params->addon_id_url);
            /** @var Product $product */
            $product = $this->em()->find('ThemeHouse\InstallAndUpgrade:Product', $product, ['Profile']);

            if($product) {
                $handler = $product->Profile->getHandler();
                $entity = $this->em()->create('ThemeHouse\InstallAndUpgrade:AddOn');
                $entity->addon_id = $addOn->getAddOnId();
                $handler->convertProductToData($product, $entity);
            }
        }
        else if ($response instanceof View)
        {
            $response->setParam('product', $product);
        }

        return $response;
    }
}