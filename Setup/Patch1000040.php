<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

trait Patch1000040
{
    public function upgrade1000040Step1()
    {
        $this->installStep1();
    }

    public function upgrade1000040Step2()
    {
        $this->installStep2();
    }
}