<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../sys/functions.php';
require_once __DIR__ . '/../../sys/core.php';
require_once __DIR__ . '/../config/config.php';

class CoreRouteTest extends PHPUnit_Framework_TestCase
{
    private $_routes;

    public static function setUpBeforeClass()
    {
        $_SERVER['SERVER_SOFTWARE'] = 'apache';
        $GLOBALS['config']['enable_rewrite'] = false;
    }

    public function setUp()
    {
        $this->_routes = array(
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
    }
    public function tearDown()
    {
        $_REQUEST = array();

        $refUrlsList = new \ReflectionProperty('App', '_urlsList');
        $refUrlsList->setAccessible(true);
        $refUrlsList->setValue(array());
        $refUrlsList->setValue(array());
    }

    public function testConstructor()
    {
        $route = $this->_newRoute();

        $this->assertArrayHasKey('user_profile', $route->getNamedRoutes());
        $this->assertArrayHasKey('default', $route->getNamedRoutes());
    }

    private function _newRoute()
    {
        $route = new Route($this->_routes);
        $route->appendDefaultRoute();
        return $route;
    }

    private function _parse()
    {
        App::urls('primary', ROOT_URL, 'q');
        $this->_newRoute()->parse();
    }

    public function testParseUserProfile()
    {
        $_GET['q'] = 'user/jason/';
        $this->_parse();
        $this->assertEquals('users', $_REQUEST['controller']);
        $this->assertEquals('profile', $_REQUEST['action']);
        $this->assertEquals('jason', $_REQUEST['username']);
    }

    public function testParseHomeWithSecondSegment()
    {
        $_GET['q'] = 'home/abc';
        $this->_parse();
        $this->assertEquals('news', $_REQUEST['controller']);
        $this->assertEquals('latest', $_REQUEST['action']);

    }

    public function testParseHome()
    {
        $_GET['q'] = 'home';
        $this->_parse();
        $this->assertEquals('news', $_REQUEST['controller']);
        $this->assertEquals('latest', $_REQUEST['action']);
    }

    public function testParseDefaultFull()
    {
        $_GET['q'] = 'a/b/c.d';
        $this->_parse();
        $this->assertEquals('a', $_REQUEST['controller']);
        $this->assertEquals('b', $_REQUEST['action']);
        $this->assertEquals('c', $_REQUEST['id']);
        $this->assertEquals('d', $_REQUEST['format']);
    }

    public function testParseDefaultToId()
    {
        $_GET['q'] = 'a/b/c';
        $this->_parse();
        $this->assertEquals('a', $_REQUEST['controller']);
        $this->assertEquals('b', $_REQUEST['action']);
        $this->assertEquals('c', $_REQUEST['id']);
        $this->assertEquals('html', $_REQUEST['format']);
    }

    public function testParseDefaultToAction()
    {
        $_GET['q'] = 'a/b';
        $this->_parse();
        $this->assertEquals('a', $_REQUEST['controller']);
        $this->assertEquals('b', $_REQUEST['action']);
        $this->assertEquals('html', $_REQUEST['format']);
    }

    public function testParseDefaultToController()
    {
        $_GET['q'] = 'a';
        $this->_parse();
        $this->assertEquals('a', $_REQUEST['controller']);
        $this->assertEquals('index', $_REQUEST['action']);
        $this->assertEquals('html', $_REQUEST['format']);
    }

    public function testParseDefaultEmpty()
    {
        $_GET['q'] = '';
        $this->_parse();
        $this->assertEquals('home', $_REQUEST['controller']);
        $this->assertEquals('index', $_REQUEST['action']);
        $this->assertEquals('html', $_REQUEST['format']);
    }
    public function testParseActivityAction()
    {
        $_GET['q'] = 'activity/b/';
        $this->_parse();
        $this->assertEquals('activity', $_REQUEST['controller']);
        $this->assertEquals('d', $_REQUEST['action']);
    }
}
