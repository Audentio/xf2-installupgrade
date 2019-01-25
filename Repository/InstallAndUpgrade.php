<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use XF\Entity\Admin;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;

class InstallAndUpgrade extends Repository
{
    public function canUseInstallUpgrade(&$error, $bypassConfig = false)
    {
        if (!$bypassConfig && !$this->app()->config('enableAddOnArchiveInstaller')) {
            $error = \XF::phrase('th_installupgrade_install_must_be_explicitly_enabled_explain');
            return false;
        }

        if (!class_exists('ZipArchive')) {
            $error = \XF::phrase('th_installupgrade_installing_is_only_supported_if_you_have_ziparchive_support');
            return false;
        }

        $root = \XF::getRootDirectory();
        $ds = DIRECTORY_SEPARATOR;

        $mustBeWritable = [
            "{$root}",
            "{$root}{$ds}js",
            "{$root}{$ds}src{$ds}addons",
            "{$root}{$ds}styles",
            __FILE__
        ];

        $writable = true;

        foreach ($mustBeWritable AS $path) {
            if (!is_writable($path)) {
                $writable = false;
                break;
            }
        }

        if (!$writable) {
            unset($mustBeWritable[0]);
            $relativePaths = array_map('XF\Util\File::stripRootPathPrefix', $mustBeWritable);

            $error = \XF::phrase('th_installupgrade_cannot_install_as_not_all_required_directories_writable',
                ['relativePaths' => implode(', ', $relativePaths)]);
            return false;
        }

        return true;
    }

    public function getProfileFromUrl($url, &$error)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\Repository\Profile $profileRepo */
        $profileRepo = $this->repository('ThemeHouse\InstallAndUpgrade:Profile');
        $profiles = $profileRepo->findProfiles()->fetch();

        foreach ($profiles as $profile) {
            if (strpos($url, $profile->base_url) === 0) {
                return $profile;
            }
        }

        $error = \XF::phrase('th_installupgrade_no_profile_found_for_url');
        return null;
    }

    /**
     * @return array
     * @throws \XF\PrintableException
     */
    public function getIndexUpdateInfo()
    {
        $products = $this->finder('ThemeHouse\InstallAndUpgrade:Product')->fetch()->groupBy('product_type');

        $updates = [
            'addOns' => [],
            'styles' => [],
            'languages' => []
        ];

        if(isset($products['addOn'])) {
            foreach ($products['addOn'] as $addOn) {
                /** @var Product $addOn */
                if ($addOn->update_available && $addOn->installed) {
                    $addOnContent = $addOn->getContent();
                    if(!$addOnContent) {
                        $addOn->bulkSet([
                            'update_available' => 0,
                            'installed' => 0
                        ]);
                        $addOn->save();
                    }
                    else {
                        $updates['addOns'][$addOn->content_id] = [
                            'addOn' => $addOnContent,
                            'product' => $addOn
                        ];
                    }
                }
            }
        }
        
        $styles = $this->finder('XF:Style')->with('THIAUProduct', true)->fetch();
        foreach ($styles as $style) {
            $product = $style->THIAUProduct;
            if ($product && !empty($product->Profile->getHandler())
                && $product->Profile->getHandler()->compareVersions($style->th_iau_current_version,
                    $product->latest_version)) {
                $updates['styles'][$style->style_id] = [
                    'style' => $style,
                    'product' => $product
                ];
            }
        }

        // TODO: Languages

        return $updates;
    }
}