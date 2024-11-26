<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use voku\helper\HtmlDomParser;

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
        return new HtmlDomParser();
    }
}