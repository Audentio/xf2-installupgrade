<?php

namespace ThemeHouse\InstallAndUpgrade\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;

/**
 * Class InstallUpgrade
 * @package ThemeHouse\InstallAndUpgrade\Api\Controller
 */
class InstallUpgrade extends AbstractController
{
    /**
     * @param $action
     * @param ParameterBag $params
     * @throws Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertApiScope('installupgrade:read');
    }

    /**
     * @param ParameterBag $params
     * @return ApiResult|AbstractReply
     */
    public function actionGet(ParameterBag $params)
    {
        switch ($params['type'])
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

    /**
     * @return ApiResult
     */
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