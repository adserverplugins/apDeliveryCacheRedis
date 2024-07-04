<?php

/**
 * apDeliveryCacheRedis for Revive Adserver
 *
 * @author Matteo Beccati
 * @license GPLv2
 * @copyright AdserverPlugins.com - All rights reserved
 *
 */

require_once dirname(__FILE__) . '/../../Redis.php';

class Test_Plugins_apDeliveryCacheRedis_Redis extends UnitTestCase
{
    public function testConnectSetGetDel()
    {
        if (!$this->assertFalse(empty($GLOBALS['_MAX']['CONF']['apRedis']), 'Missing apRedis conf section')) {
            return;
        };

        $oRedis = new AP_Redis($GLOBALS['_MAX']['CONF']['apRedis']);

        $this->assertNotNull($oRedis);

        $oRedis->setex('test', 3, 'foo');

        $this->assertEqual($oRedis->get('test'), 'foo');
        $this->assertTrue($oRedis->del('test'));
        $this->assertFalse($oRedis->get('test'));
    }
}
