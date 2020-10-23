<?php

namespace ThemeHouse\InstallAndUpgrade\Api\Controller;

use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class InstallUpgrade extends \XF\Api\Controller\AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertApiScope('installupgrade:read');
    }

    public function actionGet(ParameterBag $params)
    {
        switch ($params->type)
        {
            case 'addons':
                $entityId = 'XF:AddOn';
                break;

            case 'languages':
                $entityId = 'XF:Language';
                break;

            case 'styles':
                $entityId = 'XF:Style';
                break;

            case 'installed':
                return $this->getInstalled();
                break;

            default:
                return $this->notFound();
        }

        $all = $this->filter('all', 'bool');

        $addons = $this->finder($entityId)
            ->with('THIAUProduct', $all ? false : true)
            ->fetch();

        return $this->apiResult([
            'data' => $addons->toApiResults(),
        ]);
    }

    protected function getInstalled()
    {
        $products = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('installed', 1)
            ->order('title')
            ->fetch();

        return $this->apiResult([
            'data' => $products->toApiResults(Entity::VERBOSITY_NORMAL, ['withContent' => true]),
        ]);
    }
}