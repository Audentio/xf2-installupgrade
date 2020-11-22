<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View\Style;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Repository\Profile;

/**
 * Class Listing
 * @package ThemeHouse\InstallAndUpgrade\XF\Admin\View\Style
 *
 * @property array params
 */
class Listing extends XFCP_Listing
{
    /**
     * @throws \Exception
     */
    public function renderHtml()
    {
        if (is_callable('parent::renderHtml')) {
            parent::renderHtml();
        }

        /** @var Profile $profileRepo */
        $profileRepo = \XF::repository('ThemeHouse\InstallAndUpgrade:Profile');

        $errors = [];
        $profiles = $profileRepo
            ->findProfiles()
            ->where('last_error_messages', '!=', '[]')
            ->fetch();
        foreach ($profiles as $profile) {
            if (!empty($profile->last_error_messages['styles'])) {
                $errors[$profile->profile_id] = [
                    'errors' => $profile->last_error_messages['styles'],
                    'profile' => $profile
                ];
            }
        }

        $this->params['th_iau_errors'] = $errors;

        $styles = $this->params['styleTree'];
        $styles = $styles->getFlattened(0);

        $updates = [];
        foreach ($styles as $style) {
            /** @var Product $product */
            $product = $style['record']->THIAUProduct;

            if ($product && $product->Profile && !empty($product->Profile->getHandler())
                && $product->Profile->getHandler()->compareVersions($style['record']->th_iau_current_version,
                    $product->latest_version)) {
                $updates[] = $style['record'];
            }
        }

        $this->params['updates'] = $updates;
    }
}
