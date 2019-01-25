<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

trait VersioningTrait
{
    /**
     * @param $currentVersion
     * @param $latestVersion
     * @param string $operator
     * @return mixed
     */
    public function compareVersions($currentVersion, $latestVersion, $operator = '<')
    {
        return version_compare($this->standardizeVersionNumber($currentVersion),
            $this->standardizeVersionNumber($latestVersion), $operator);
    }

    /**
     * @param $versionNumber
     * @return mixed
     */
    protected function standardizeVersionNumber($versionNumber)
    {
        $versionNumber = str_replace(' ', '', $versionNumber);
        $versionNumber = str_ireplace('Alpha', 'a', $versionNumber);
        $versionNumber = str_ireplace('Beta', 'b', $versionNumber);
        $versionNumber = str_ireplace('ReleaseCandidate', 'rc', $versionNumber);
        $versionNumber = str_ireplace('PatchLevel', 'pl', $versionNumber);
        return $versionNumber;
    }
}