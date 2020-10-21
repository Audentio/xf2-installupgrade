<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

/**
 * Trait Patch1010670
 * @package ThemeHouse\InstallAndUpgrade\Setup
 */
trait Patch1010670
{
    /**
     *
     */
    public function upgrade1010670Step1()
    {
        $this->installStep2();
    }
}