<?php

namespace ThemeHouse\InstallAndUpgrade\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Class Log
 * @package ThemeHouse\InstallAndUpgrade\Admin\Controller
 */
class Log extends AbstractController
{
    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionIndex(ParameterBag $params)
    {
        if ($params['log_id']) {
            $entry = $this->assertLogExists($params['log_id'], null, 'requested_log_entry_not_found');

            $viewParams = [
                'entry' => $entry
            ];
            return $this->view('ThemeHouse\InstallAndUpgrade:Log\View', 'th_iau_log_view', $viewParams);
        } else {
            $page = $this->filterPage();
            $perPage = 20;

            /** @var \ThemeHouse\InstallAndUpgrade\Repository\Log $logRepo */
            $logRepo = $this->repository('ThemeHouse\InstallAndUpgrade:Log');

            $logFinder = $logRepo->findLogs()
                ->limitByPage($page, $perPage);

            $linkFilters = [];
            if ($userId = $this->filter('user_id', 'uint')) {
                $linkFilters['user_id'] = $userId;
                $logFinder->where('user_id', $userId);
            }

            if ($action = $this->filter('action', 'str')) {
                $linkFilters['action'] = $action;
                $logFinder->where('action', $action);
            }

            if ($this->isPost()) {
                return $this->redirect($this->buildLink('th-install-upgrade-log', null, $linkFilters));
            }

            $viewParams = [
                'entries' => $logFinder->fetch(),
                'userId' => $userId,
                'action' => $action,
                'contentTypes' => $this->getContentTypeFilters(),

                'page' => $page,
                'perPage' => $perPage,
                'total' => $logFinder->total(),
                'linkFilters' => $linkFilters
            ];
            return $this->view('ThemeHouse\InstallAndUpgrade:Log\Listing', 'th_iau_log_list', $viewParams);
        }
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return \XF\Mvc\Entity\Entity
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertLogExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:Log', $id, $with, $phraseKey);
    }

    /**
     * @return array
     */
    protected function getContentTypeFilters()
    {
        return [
            'addOn' => \XF::phrase('add_on'),
            'style' => \XF::phrase('style'),
            'language' => \XF::phrase('language')
        ];
    }

    /**
     * @param $action
     * @param ParameterBag $params
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('viewLogs');
    }
}
