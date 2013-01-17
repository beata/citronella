<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../../../sys/functions.php';
require_once __DIR__ . '/../../../../sys/core.php';
require_once __DIR__ . '/../../../config/config.php';

class Sys_Core_Urls_BasicTest extends PHPUnit_Framework_TestCase
{
    private static $origEnv;

    public static function setUpBeforeClass()
    {
        self::$origEnv['enable_rewrite'] = $GLOBALS['config']['enable_rewrite'];
        $GLOBALS['config']['enable_rewrite'] = App::conf()->enable_rewrite = false;
    }
    public static function tearDownAfterClass()
    {
        $GLOBALS['config']['enable_rewrite'] = App::conf()->enable_rewrite = self::$origEnv['enable_rewrite'];
    }

    public function testConstructor()
    {
        $reflection_class = new \ReflectionClass('Urls');

        foreach (array('_urlBase', '_modRewriteEnabled', '_paramName', '_segments', '_queryString', '_queryStringPrefix') as $var) {
            $$var = $reflection_class->getProperty($var);
            $$var->setAccessible(true);
        }

        $urls = new Urls('/tests', 's');

        $this->assertEquals('/tests/', $_urlBase->getValue($urls));
        $this->assertEquals($GLOBALS['config']['enable_rewrite'], $_modRewriteEnabled->getValue($urls));
        $this->assertFalse($_modRewriteEnabled->getValue($urls));
        $this->assertEquals('s', $_paramName->getValue($urls));

        // case 1
        if ( isset($_GET['s'])) {
            unset($_GET['s']);
        }
        $urls = new Urls('/tests', 's');
        $this->assertEquals('', $_queryString->getValue($urls));
        $this->assertNull($_queryStringPrefix->getValue($urls));
        $this->assertEquals(array( 0 => ''), $_segments->getValue($urls));

        // case 2
        $_GET['s'] = '';
        $urls = new Urls('/tests', 's');
        $this->assertEquals('', $_queryString->getValue($urls));
        $this->assertNull($_queryStringPrefix->getValue($urls));
        $this->assertEquals(array(''), $_segments->getValue($urls));

        // case 3
        $_GET['s'] = 's1/s2/s3/s4.ext';
        $urls = new Urls('/tests', 's');
        $this->assertEquals('s1/s2/s3/s4.ext', $_queryString->getValue($urls));
        $this->assertNull($_queryStringPrefix->getValue($urls));
        $this->assertEquals(array( 's1', 's2', 's3', 's4.ext'), $_segments->getValue($urls));
    }
    public function testGetSegment()
    {
        $_GET['s'] = 's1/s2/s3/s4.ext';
        $urls = new Urls('/tests', 's');

        $this->assertEquals('', $urls->segment(10));
        $this->assertEquals('x', $urls->segment(10, 'x'));
        $this->assertEquals('s3', $urls->segment(2));
        $this->assertEquals('s3', $urls->segment(2, 'x'));
    }

    public function testUrlto()
    {
        $urls = new Urls('/tests', 'q');

        // case 1
        $this->assertEquals('/tests/?q=a/b', $urls->urlto('a/b'));

        // case 2
        $urls->setQueryStringPrefix('pre');
        $this->assertEquals('/tests/?q=pre', $urls->urlto(''));
        $this->assertEquals('/tests/?q=pre/a/b', $urls->urlto('a/b'));
        $urls->setQueryStringPrefix(null);

        // case 3
        $this->assertEquals('/tests/?q=a/b', $urls->urlto('a/b'));
        $this->assertEquals('/tests/?p1=v1&amp;p2=v2&amp;q=a/b', $urls->urlto('a/b', array('p1' => 'v1', 'p2' => 'v2')));


        // case 4
        $this->assertEquals('/tests/?q=a/b', $urls->urlto('a/b'));
        $this->assertEquals('/tests/?p1=v1&p2=v2&q=a/b', $urls->urlto('a/b', array('p1' => 'v1', 'p2' => 'v2'), array('argSeparator' => '&')));


        // case 5
        $this->assertEquals('/tests/?q=a/b', $urls->urlto('a/b'));
        $_REQUEST['is_ssl'] = true;
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals('https://example.com/tests/?q=a/b', $urls->urlto('a/b', null, array('fullurl' => true)));

        // case 6
        unset($_REQUEST['is_ssl']);
        $this->assertEquals('http://example.com/tests/?q=a/b', $urls->urlto('a/b', null, array('fullurl' => true)));
    }
}
