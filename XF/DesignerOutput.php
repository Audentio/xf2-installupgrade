<?php

namespace ThemeHouse\InstallAndUpgrade\XF;

use ThemeHouse\InstallAndUpgrade\XF\Entity\Style;
use XF\Mvc\Entity\Entity;

class DesignerOutput extends XFCP_DesignerOutput
{
    /**
     * @param Entity $entity
     * @return mixed
     */
    public function export(Entity $entity)
    {
        if ($entity->Style) {
            $entity->Style->exportInstallUpgradeJSON();
        }

        return parent::export($entity);
    }

    /**
     * @param $shortName
     * @param $name
     * @param $styleId
     * @param $contents
     * @param array $metadata
     * @param array $options
     * @return mixed
     * @throws \XF\PrintableException
     */
    public function import($shortName, $name, $styleId, $contents, array $metadata, array $options = [])
    {
        $style = \XF::em()->find('XF:Style', $styleId);
        /** @var Style $style */
        $style->importInstallUpgradeJSON();

        return parent::import($shortName, $name, $styleId, $contents, $metadata, $options);
    }
}