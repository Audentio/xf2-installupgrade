<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use Exception;
use XF\DB\AbstractAdapter;

/**
 * Class DbLogQuery
 * @package ThemeHouse\InstallAndUpgrade\Repository
 *
 * This class is used to reset the query log which XF doesn't provide a sane way to zap
 */
abstract class DbLogQuery extends AbstractAdapter
{
    /**
     * @param AbstractAdapter $db
     */
    public static function resetQueryLog(AbstractAdapter $db)
    {
        try {
            $db->queryLog = [];
        } catch (Exception $e) {

        }
    }
}