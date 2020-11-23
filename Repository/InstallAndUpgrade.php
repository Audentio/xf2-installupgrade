<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF\Mvc\Entity\Repository;

/**
 * Class InstallAndUpgrade
 * @package ThemeHouse\InstallAndUpgrade\Repository
 */
class InstallAndUpgrade extends Repository
{
    /**
     * @param $error
     * @param bool $bypassConfig
     * @return bool
     */
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

    /**
     * @param $url
     * @param $error
     * @return mixed|null
     */
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

        if (isset($products['addOn'])) {
            foreach ($products['addOn'] as $addOn) {
                /** @var Product $addOn */
                if ($addOn->update_available && $addOn->installed) {
                    $addOnContent = $addOn->getContent();
                    if (!$addOnContent) {
                        $addOn->bulkSet([
                            'update_available' => 0,
                            'installed' => 0
                        ]);
                        $addOn->save();
                    } else {
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
            if ($product && $product->Profile && !empty($product->Profile->getHandler())
                && $product->Profile->getHandler()->compareVersions($style->th_iau_current_version,
                    $product->latest_version)) {
                $updates['styles'][$style->style_id] = [
                    'style' => $style,
                    'product' => $product
                ];
            }
        }

        $languages = $this->finder('XF:Language')->with('THIAUProduct', true)->fetch();
        foreach ($languages as $language) {
            $product = $language->THIAUProduct;
            if ($product && $product->Profile && !empty($product->Profile->getHandler())
                && $product->Profile->getHandler()->compareVersions($product->current_version,
                    $product->latest_version)) {
                $updates['languages'][$language->language_id] = [
                    'language' => $language,
                    'product' => $product
                ];
            }
        }

        return $updates;
    }

    /**
     * @param \XF\AddOn\AddOn[] $upgradeableAddOns
     * @return array
     */
    public function sortByDependencies(array $upgradeableAddOns)
    {
        $installList = [];
        /** @var \XF\AddOn\AddOn[] $complex */
        $complex = [];
        // init the list
        foreach ($upgradeableAddOns as $addOn) {
            $json = $addOn->getJson();
            unset($json['require']['php']);
            unset($json['require']['XF']);
            $installList[$addOn->getAddOnId()] = ['addon' => $addOn, 'dependencies' => []];
            if (!empty($json['require']) || !empty($json['require-soft'])) {
                $complex[$addOn->getAddOnId()] = $addOn;
            }
        }
        // build the graph
        foreach ($complex as $addOnId => $addOn) {
            $json = $addOn->getJson();
            foreach ((array)$json['require'] as $productKey => $requirement) {
                if (empty($installList[$productKey])) {
                    continue;
                }
                if (empty($installList[$addOnId]['dependencies'][$productKey])) {
                    $installList[$addOnId]['dependencies'][$productKey] = &$installList[$productKey];
                }
            }
            // custom install hints
            if (isset($json['require-soft'])) {
                foreach ((array)$json['require-soft'] as $productKey => $requirement) {
                    if (empty($installList[$productKey])) {
                        continue;
                    }
                    if (empty($installList[$addOnId]['dependencies'][$productKey])) {
                        $installList[$addOnId]['dependencies'][$productKey] = &$installList[$productKey];
                    }
                }
            }
        }

        // actually resolve into a list
        $finalList = [];
        $loopDetection = [];
        foreach ($installList as $addOnId => $addOn) {
            if ($addOn['dependencies']) {
                $finalList = $finalList + $this->resolveDependencies($installList, $addOnId, $loopDetection);
            }

            $finalList[$addOnId] = $addOn['addon'];
        }

        return $finalList;
    }

    /**
     * @param array $installList
     * @param string $addOnId
     * @param array $loopDetection
     * @return array
     */
    protected function resolveDependencies(array $installList, $addOnId, array &$loopDetection)
    {
        $loopDetection[$addOnId] = true;
        $finalList = [];
        foreach ($installList[$addOnId]['dependencies'] as $childAddOnId => $addOn) {
            if (isset($loopDetection[$childAddOnId])) {
                continue;
            }

            if ($addOn['dependencies']) {
                $finalList = $finalList + $this->resolveDependencies($installList, $childAddOnId, $loopDetection);
            }

            $finalList[$childAddOnId] = $addOn['addon'];
        }

        return $finalList;
    }
}