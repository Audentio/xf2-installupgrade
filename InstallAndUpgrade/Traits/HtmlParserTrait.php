<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use PHPHtmlParser\Dom;

trait HtmlParserTrait
{
    /**
     * @return Dom
     */
    public function htmlParser()
    {
        return new Dom();
    }
}