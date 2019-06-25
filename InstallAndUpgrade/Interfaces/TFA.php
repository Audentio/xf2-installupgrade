<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

/**
 * Interface TFA
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces
 */
interface TFA
{
    /**
     * @param $code
     * @return mixed
     */
    public function setTfaCode($code);

    /**
     * @return mixed
     */
    public function getTfaCode();
}