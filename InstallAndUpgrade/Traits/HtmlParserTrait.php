<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use PHPHtmlParser\Dom;

/**
 * Trait HtmlParserTrait
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits
 */
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