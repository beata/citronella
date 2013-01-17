<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../../../sys/functions.php';
require_once __DIR__ . '/../../../../sys/core.php';
require_once __DIR__ . '/../../../config/config.php';

class Sys_Core_Urls_NamedRoutesTest extends PHPUnit_Framework_TestCase
{
    private static $origEnv;

    public static function setUpBeforeClass()
    {
        self::$origEnv['SERVER_SOFTWARE'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : null;
        self::$origEnv['enable_rewrite'] = $GLOBALS['config']['enable_rewrite'];

        $_SERVER['SERVER_SOFTWARE'] = 'apache';
        $GLOBALS['config']['enable_rewrite'] = App::conf()->enable_rewrite = false;
    }
    public static function tearDownAfterClass()
    {
        $_SERVER['SERVER_SOFTWARE'] = self::$origEnv['SERVER_SOFTWARE'];
        if ( NULL === $_SERVER['SERVER_SOFTWARE']) {
            unset($_SERVER['SERVER_SOFTWARE']);
        }
        $GLOBALS['config']['enable_rewrite'] = App::conf()->enable_rewrite = self::$origEnv['enable_rewrite'];
    }

    public function setUp()
    {
        $routes = array(
            '__defaultParams' => array(
                'controller' => 'home',
                'action' => 'index',
                'format' => 'html',
            ),
            'user/(?P<username>[^/]+)' => array(
                'controller' => 'users',
                'action' => 'profile',
                '__id' => 'user_profile',
            ),
            'activity/(?P<action>a|b|c)' => array(
                'controller' => 'activity',
                'action' => 'd',
                '__id' => 'activity_action',
            ),
            'home' => array(
                'controller' => 'news',
                'action' => 'latest',
            ),
        );
        App::route($routes)->appendDefaultRoute();
        App::urls('primary');
    }

    public function testDefault()
    {

        // empty controller, action and id
        $this->assertEquals('/', App::urls()->urltoId('default'));
        $this->assertEquals('/?q=home.xml', App::urls()->urltoId('default', array(
            'format' => 'xml'
        )));

        // controller
        $this->assertEquals('/?q=a', App::urls()->urltoId('default', array(
            'controller' => 'a',
        )));
        $this->assertEquals('/?q=a.xml', App::urls()->urltoId('default', array(
            'controller' => 'a',
            'format' => 'xml'
        )));

        // controller + action
        $this->assertEquals('/?q=a/b', App::urls()->urltoId('default', array(
            'controller' => 'a',
            'action' => 'b',
        )));
        $this->assertEquals('/?q=a/b.xml', App::urls()->urltoId('default', array(
            'controller' => 'a',
            'action' => 'b',
            'format' => 'xml'
        )));

        // controller + action + id
        $this->assertEquals('/?q=a/b/c', App::urls()->urltoId('default', array(
            'controller' => 'a',
            'action' => 'b',
            'id' => 'c',
        )));
        $this->assertEquals('/?q=a/b/c.xml', App::urls()->urltoId('default', array(
            'controller' => 'a',
            'action' => 'b',
            'id' => 'c',
            'format' => 'xml'
        )));
    }

    public function testUserProfile()
    {
        $this->assertEquals('/?q=user/jay', App::urls()->urltoId('user_profile', array(
            'username' => 'jay'
        )));
    }

    public function testActivityAction()
    {
        $this->assertEquals('/?q=activity/a', App::urls()->urltoId('activity_action', array(
            'action' => 'a'
        )));
    }

}
