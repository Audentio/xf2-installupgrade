<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use PHPHtmlParser\Dom;

trait HtmlParserTrait
{
    public function htmlParser()
    {
        return new Dom();
    }
}