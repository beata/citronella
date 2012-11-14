<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../sys/functions.php';
require_once __DIR__ . '/../../sys/core.php';
require_once __DIR__ . '/../config/config.php';

class CoreAppTest extends PHPUnit_Framework_TestCase
{

    public static function setUpBeforeClass()
    {
        $_SERVER['SERVER_SOFTWARE'] = 'apache';
        $GLOBALS['config']['enable_rewrite'] = false;
    }

    public function testBoot()
    {
        App::prepare();

        $this->assertArrayHasKey('is_ajax', $_REQUEST);
        $this->assertArrayHasKey('is_ssl', $_REQUEST);
        $this->assertArrayHasKey('IS_IIS', $_SERVER);


        $_SERVER['SERVER_SOFTWARE'] = 'microsoft-iis';
        App::prepare();
        $this->assertArrayHasKey('REQUEST_URI', $_SERVER);
    }

}
