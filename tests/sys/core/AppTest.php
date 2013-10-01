<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../../source/sys/functions.php';
require_once __DIR__ . '/../../../source/sys/core.php';
require_once __DIR__ . '/../../config/config.php';

class Sys_Core_AppTest extends PHPUnit_Framework_TestCase
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

    public function testArray2Obj()
    {
        $method = new \ReflectionMethod('App', '_array2Obj');
        $method->setAccessible(true);
        $result = $method->invokeArgs(NULL, array(
            array(
                'a' => 1,
                'b' => array(
                    'a' => 1,
                    'b' => array(
                        'a' => 1,
                        'string',
                        3
                    ),
                    'c' => array(
                        1,
                        'key'=>'string'
                    )
                ),
            )
        ));

        $expects = new stdclass;
        $expects->a = 1;
        $expects->b = new stdclass;
        $expects->b->a = 1;
        $expects->b->b = new stdclass;
        $expects->b->b->a = 1;
        $expects->b->b->{'0'} = 'string';
        $expects->b->b->{'1'} = 3;
        $expects->b->c = array(
            1,
            'key' => 'string'
        );

        $this->assertEquals($expects, $result);

    }

}
