<?php

/**
 * apDeliveryCacheRedis for Revive Adserver
 *
 * @author Matteo Beccati
 * @license GPLv2
 * @copyright AdserverPlugins.com - All rights reserved
 *
 */


require_once LIB_PATH . '/Extension/deliveryCacheStore/DeliveryCacheStore.php';
// Using multi-dirname so tests can be run from either plugins or plugins_repo
require_once dirname(__FILE__) . '/apRedis.delivery.php';


class Plugins_DeliveryCacheStore_apRedis_apRedis extends Plugins_DeliveryCacheStore
{
    public function getName()
    {
        return 'Redis';
    }

    public function getStatus()
    {
        try {
            AP_Redis_Cache::singleton();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function _deleteCacheFile($filename)
    {
        $redis = AP_Redis_Cache::singleton();

        return $redis->del($filename);
    }

    public function _deleteAll()
    {
        $redis = AP_Redis_Cache::singleton();

        return $redis->flushdb();
    }
}
