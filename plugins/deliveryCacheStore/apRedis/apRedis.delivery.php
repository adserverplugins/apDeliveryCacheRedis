<?php

/**
 * apDeliveryCacheRedis for Revive Adserver
 *
 * @author Matteo Beccati
 * @license GPLv2
 * @copyright AdserverPlugins.com - All rights reserved
 *
 */


if (!class_exists('AP_Redis')) {
    require_once MAX_PATH . '/plugins/apRedis/Redis.php';
}


class AP_Redis_Cache extends AP_Redis
{
    private static $instance;

    public static function singleton(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}


function Plugin_deliveryCacheStore_apRedis_apRedis_Delivery_cacheRetrieve($filename)
{
    $expiryTime = $GLOBALS['OA_Delivery_Cache']['expiry'];
    $now = MAX_commonGetTimeNow();

    try {
        $redis = AP_Redis_Cache::singleton();

        $serializedCacheVar = $redis->get($filename);
        if (!empty($serializedCacheVar)) {
            $data = $redis->unserialize($serializedCacheVar);
            if ((isset($data['cache_time']) && $data['cache_time'] + $expiryTime < $now)
             || (isset($data['cache_expire']) && $data['cache_expire'] < $now)) {
                // The cache entry is expired, try to acquire a lock by setting
                // a lock variable, if not already set
                $lock = '_lock_' . $filename;
                if ($redis->setnx($lock, 1)) {
                    // Failsafe, make sure that the lock expires after a few
                    // seconds to avoid a cache entry being permanently locked
                    // in case of issues
                    $redis->expire($lock, 10);

                    return false;
                } else {
                    // The lock couldn't be acquired. Return the stale data by
                    // unsetting the expiry time
                    unset($data['cache_time']);
                    unset($data['cache_expire']);
                }
            }

            return $data;
        }
    } catch (Exception $e) {
    }

    return false;
}

function Plugin_deliveryCacheStore_apRedis_apRedis_Delivery_cacheStore($filename, $cache_contents)
{
    $expiryTime = $GLOBALS['OA_Delivery_Cache']['expiry'];

    try {
        $redis = AP_Redis_Cache::singleton();

        if (isset($cache_contents['cache_expire'])) {
            $expiryTime = $cache_contents['cache_expire'];
        } elseif (isset($cache_contents['cache_time'])) {
            $expiryTime = $cache_contents['cache_time'] - MAX_commonGetTimeNow() + $GLOBALS['OA_Delivery_Cache']['expiry'];
        }

        $result = $redis->setex($filename, $expiryTime + 86400, $redis->serialize($cache_contents));
        $redis->del('_lock_' . $filename);

        return $result;
    } catch (Exception $e) {
        return false;
    }
}
