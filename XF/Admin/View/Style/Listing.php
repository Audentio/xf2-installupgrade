<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View\Style;

use ThemeHouse\InstallAndUpgrade\Entity\Product;

class Listing extends XFCP_Listing
{
    public function renderHtml()
    {
        if (is_callable('parent::renderHtml'))
        {
            parent::renderHtml();
        }
    
        $styles = $this->params['styleTree'];
        $styles = $styles->getFlattened(0);
    
        $updates = [];
        foreach ($styles as $style) {
            /** @var Product $product */
            $product = $style['record']->THIAUProduct;
        
            if ($product && !empty($product->Profile->getHandler())
                && $product->Profile->getHandler()->compareVersions($style['record']->th_iau_current_version,
                    $product->latest_version)) {
                $updates[] = $style['record'];
            }
        }
    
        $this->params['updates'] = $updates;
    }
}
