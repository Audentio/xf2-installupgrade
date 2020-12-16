<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View\Language;

use Exception;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Repository\Profile;
use XF;

/**
 * Class Listing
 * @package ThemeHouse\InstallAndUpgrade\XF\Admin\View\Language
 *
 * @property array $params
 */
class Listing extends XFCP_Listing
{
    /**
     * @throws Exception
     */
    public function renderHtml()
    {
        if (is_callable('parent::renderHtml')) {
            /** @noinspection PhpUndefinedMethodInspection */
            parent::renderHtml();
        }

        /** @var Profile $profileRepo */
        $profileRepo = XF::repository('ThemeHouse\InstallAndUpgrade:Profile');

        $errors = [];
        $profiles = $profileRepo
            ->findProfiles()
            ->where('last_error_messages', '!=', '[]')
            ->fetch();
        foreach ($profiles as $profile) {
            if (!empty($profile->last_error_messages['languages'])) {
                $errors[$profile->profile_id] = [
                    'errors' => $profile->last_error_messages['languages'],
                    'profile' => $profile
                ];
            }
        }

        $this->params['th_iau_errors'] = $errors;

        $languages = $this->params['languageTree'];
        $languages = $languages->getFlattened(0);

        $updates = [];
        foreach ($languages as $language) {
            /** @var Product $product */
            $product = $language['record']->THIAUProduct;

            if ($product && !empty($product->Profile->getHandler())
                && $product->update_available) {
                $updates[] = $language['record'];
            }
        }

        $this->params['updates'] = $updates;
    }
}
