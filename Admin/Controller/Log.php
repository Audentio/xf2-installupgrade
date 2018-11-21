<?php

namespace ThemeHouse\InstallAndUpgrade\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Log extends AbstractController
{
    protected function cleanLog() {
        $db = $this->app->db();
        $addOns = $db->query('SELECT addon_id FROM xf_addon')->fetchAllColumn(0);
        $ids = join("','", $addOns);
        $db->delete('xf_th_installupgrade_log', "content_type = 'addOn' && content_id NOT IN ('{$ids}')");

        $styles = $db->query('SELECT style_id FROM xf_style')->fetchAllColumn(0);
        $ids = join("','", $styles);
        $db->delete('xf_th_installupgrade_log', "content_type = 'style' && content_id NOT IN ('{$ids}')");


        $languages = $db->query('SELECT language_id FROM xf_language')->fetchAllColumn(0);
        $ids = join("','", $languages);
        $db->delete('xf_th_installupgrade_log', "content_type = 'language' && content_id NOT IN ('{$ids}')");
    }

    /**
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionIndex()
    {
        $this->cleanLog();
        
        $page = $this->filterPage();
        $perPage = 20;
        $filters = [];

        $entries = $this->finder('ThemeHouse\InstallAndUpgrade:Log')
            ->order('timestamp', 'DESC')
            ->with('Style')->with('AddOn')->with('Language')
            ->limitByPage($page, $perPage);

        if ($contentType = $this->filter('content_type', 'str')) {
            $entries->where('content_type', $contentType);
            $filters['content_type'] = $contentType;
        }

        if ($username = $this->filter('username', 'str')) {
            /** @var \XF\Entity\User $user */
            $user = $this->finder('XF:User')->where('username', $username)->fetchOne();
            if ($user) {
                $entries->where('user_id', $user->user_id);
                $filters['username'] = $user->username;
            }
        }

        if ($start = $this->filter('start', 'datetime')) {
            $entries->where('timestamp', '>', $start);
            $filters['start'] = $start;
        }

        if ($end = $this->filter('end', 'datetime')) {
            $entries->where('timestamp', '<', $end);
            $filters['end'] = $end;
        }

        if ($filters && $this->isPost()) {
            return $this->redirect($this->buildLink('install-upgrade-log', null, $filters), '');
        }

        $viewParams = [
            'entries' => $entries->fetch(),
            'filters' => $filters,

            'page' => $page,
            'perPage' => $perPage,
            'total' => $entries->total()
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Log\Listing', 'th_iau_install_upgrade_log_list', $viewParams);
    }

    /**
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     */
    public function actionClear()
    {
        if ($this->isPost()) {
            $this->getLogRepo()->clearLog();

            return $this->redirect($this->buildLink('install-upgrade-log'));
        } else {
            return $this->view('ThemeHouse\InstallAndUpgrade:Log\Clear', 'th_iau_install_upgrade_log_clear');
        }
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionView(ParameterBag $params)
    {
        $entry = $this->assertLogExists($params->log_id);

        $viewParams = [
            'entry' => $entry
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Log\View', 'th_iau_install_upgrade_log_view', $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionDelete(ParameterBag $params)
    {
        $entry = $this->assertLogExists($params->log_id);

        if ($this->isPost()) {
            $entry->delete();

            return $this->redirect($this->buildLink('install-upgrade-log'));
        } else {

            $viewParams = [
                'entry' => $entry
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Log\Delete', 'th_iau_install_upgrade_log_delete',
                $viewParams);
        }
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return \ThemeHouse\InstallAndUpgrade\Entity\Log
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertLogExists($id, $with = null, $phraseKey = null)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:Log', $id, $with, $phraseKey);
    }

    /**
     * @return \ThemeHouse\InstallAndUpgrade\Repository\Log
     */
    protected function getLogRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\InstallAndUpgrade:Log');
    }
}