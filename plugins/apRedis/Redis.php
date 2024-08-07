<?php

/**
 * apDeliveryCacheRedis for Revive Adserver
 *
 * @author Matteo Beccati
 * @license GPLv2
 * @copyright AdserverPlugins.com - All rights reserved
 *
 */


class AP_Redis
{
    public const TYPE_EXT = 0;
    public const TYPE_PHP = 1;

    protected $type;
    protected $redis;
    protected $igbinary;

    public function __construct($aConf = null, $default = 'apRedis')
    {
        if (!isset($aConf)) {
            $aConf = $GLOBALS['_MAX']['CONF'][$default];
        }

        $this->igbinary = !empty($aConf['igbinary']) && extension_loaded('igbinary');

        if (!empty($aConf['socket'])) {
            $host = $aConf['socket'];
            $port = null;
        } else {
            $host = $aConf['host'];
            $port = $aConf['port'];
        }

        if (extension_loaded('redis')) {
            $this->type = self::TYPE_EXT;
            $this->redis = new Redis();
            $method = empty($aConf['persistent']) ? 'connect' : 'pconnect';
            $this->redis->$method($host, $port, $aConf['timeout']);
        } else {
            $this->type = self::TYPE_PHP;
            if (!class_exists('Redis')) {
                include __DIR__ . '/Redisent/Redis.php';
            }
            $this->redis = new Redis($host, $port, $aConf['timeout']);
        }

        if (!empty($aConf['database']) || !empty($aConf['persistent'])) {
            $this->redis->select($aConf['database']);
        }
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->redis, $name], $arguments);
    }

    public function serialize($data)
    {
        if ($this->igbinary) {
            return igbinary_serialize($data);
        }

        return serialize($data);
    }

    public function unserialize($data)
    {
        if ($this->igbinary) {
            return igbinary_unserialize($data);
        }

        return unserialize($data);
    }

    public function getType()
    {
        return $this->type;
    }
}
