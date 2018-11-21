<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use XF\Entity\Admin;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;

class InstallAndUpgrade extends Repository
{
    /**
     * @param User $user
     * @param $type
     * @param $action
     * @param array $extra
     * @return bool
     */
    public function sendAdminAlert(User $user, $type, $action, array $extra = [])
    {
        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->alert(
            $user,
            0, '',
            'user', $user->user_id,
            "th_iau_{$type}_{$action}", $extra
        );

        return true;
    }

    /**
     * @param $url
     * @return mixed|null|Profile
     */
    public function getProfileFromUrl($url)
    {
        $finder = $this->finder('ThemeHouse\InstallAndUpgrade:Profile');

        $profiles = $finder->fetch();
        foreach ($profiles as $profile) {
            /** @var Profile $profile */
            if (strpos($profile->base_url, $url) === 0) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * @param $type
     * @param $action
     * @param array $extra
     */
    public function sendAdminAlerts($type, $action, array $extra = [])
    {
        $admins = $this->finder('XF:Admin')
            ->with('User')
            ->fetch();

        foreach ($admins as $admin) {
            /** @var Admin $admin */
            if ($admin->hasAdminPermission($type)) {
                $this->sendAdminAlert($admin->User, $type, $action, $extra);
            }
        }
    }

    /**
     * @return null|\XF\Mvc\Entity\Entity
     */
    public function getAvailableXenForoUpgrade()
    {
        $finder = $this->finder('ThemeHouse\InstallAndUpgrade:AddOn');

        $finder
            ->with('AddOn', true)
            ->where('addon_id', '=', 'XF')
            ->where('update_check', '=', 1)
            ->where('update_available', '=', 1);

        return $finder->fetchOne();
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function getAvailableAddOnUpgrades()
    {
        $finder = $this->finder('ThemeHouse\InstallAndUpgrade:AddOn');

        $finder
            ->with('AddOn', true)
            ->where('addon_id', '<>', 'XF')
            ->where('update_check', '=', 1)
            ->where('update_available', '=', 1)
            ->setDefaultOrder('AddOn.title');

        return $finder;
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function getAvailableStyleUpgrades()
    {
        $finder = $this->finder('ThemeHouse\InstallAndUpgrade:Style');

        $finder
            ->with('Style', true)
            ->where('update_check', '=', 1)
            ->where('update_available', '=', 1)
            ->setDefaultOrder('Style.title');

        return $finder;
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function getAvailableLanguageUpgrades()
    {
        $finder = $this->finder('ThemeHouse\InstallAndUpgrade:Language');

        $finder
            ->with('Language', true)
            ->where('update_check', '=', 1)
            ->where('update_available', '=', 1)
            ->setDefaultOrder('Language.title');

        return $finder;
    }
}