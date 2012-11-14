<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../sys/functions.php';
require_once __DIR__ . '/../../sys/core.php';
require_once __DIR__ . '/../config/config.php';

class CoreAclTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $rules = array(
            '__default' => array(
                'allow' => '*',
                'deny' => '*',
                '__failRoute' => '404',
            ),
            'admin' => array(
                'allow' => '*',
                'deny' => array( // 越後面的設定會蓋掉前面的設定
                    'auth/login', // $_REQUEST['controller']/$_REQUEST['action']
                    'auth/reset-password-auth',
                    'auth/reset-password' ),
            ),
            'writer' => array(
                'allow' => 'articles/*',
                'deny' => array(
                    'auth/login',
                    'auth/reset-password-auth',
                    'auth/reset-password' ),
                '__failRoute' => 'forbid-access'
            ),
            'anonymous' => array(
                'deny' => '*',
                'allow' => array(
                    'auth/login',
                    'auth/reset-password-auth',
                    'auth/reset-password',
                ),
                '__failRoute' => 'auth/login'
            )
        );

        App::route(array(
            '__defaultParams' => array(
                'controller' => 'home',
                'action' => 'index',
                'format' => 'html',
            )
        ));

        $this->_acl = new Acl($rules);


        $this->_getRoleRules = new \ReflectionMethod('Acl', '_getRoleRules');
        $this->_getRoleRules->setAccessible(true);

        $this->_isAccessible = new \ReflectionMethod('Acl', '_isAccessible');
        $this->_isAccessible->setAccessible(true);
    }

    public function testAdmin()
    {
        $rule = $this->_getRoleRules->invokeArgs($this->_acl, array('admin'));
        $this->assertEquals(array(
            '__failRoute' => '404',
            'allow' => '*',
            'deny' => array(
                'auth/login',
                'auth/reset-password-auth',
                'auth/reset-password' ),
        ), $rule);


        $_REQUEST['controller'] = 'aa';
        $_REQUEST['action'] = 'bb';
        $this->assertTrue($this->_isAccessible->invokeArgs($this->_acl, array($rule)));

        $_REQUEST['controller'] = 'auth';
        $_REQUEST['action'] = 'login';
        $this->assertFalse($this->_isAccessible->invokeArgs($this->_acl, array($rule)));
    }

    public function testWriter()
    {
        $rule = $this->_getRoleRules->invokeArgs($this->_acl, array('writer'));
        $this->assertEquals(array(
            'allow' => 'articles/*',
            'deny' => array(
                'auth/login',
                'auth/reset-password-auth',
                'auth/reset-password' ),
            '__failRoute' => 'forbid-access',
        ), $rule);

        $_REQUEST['controller'] = 'articles';
        $_REQUEST['action'] = 'aa';
        $this->assertTrue($this->_isAccessible->invokeArgs($this->_acl, array($rule)));

        $_REQUEST['controller'] = 'aa';
        $_REQUEST['action'] = 'bb';
        $this->assertFalse($this->_isAccessible->invokeArgs($this->_acl, array($rule)));

        $_REQUEST['controller'] = 'auth';
        $_REQUEST['action'] = 'login';
        $this->assertFalse($this->_isAccessible->invokeArgs($this->_acl, array($rule)));

        $this->_acl->setRole('writer');
        $this->_acl->check();
        $this->assertEquals('forbid-access', $_REQUEST['controller']);
        $this->assertEquals('index', $_REQUEST['action']);
    }

    public function testAnonymous()
    {
        $rule = $this->_getRoleRules->invokeArgs($this->_acl, array('anonymous'));
        $this->assertEquals(array(
            'deny' => '*',
            'allow' => array(
                'auth/login',
                'auth/reset-password-auth',
                'auth/reset-password',
            ),
            '__failRoute' => 'auth/login'
        ), $rule);

        $_REQUEST['controller'] = 'articles';
        $_REQUEST['action'] = 'aa';
        $this->assertFalse($this->_isAccessible->invokeArgs($this->_acl, array($rule)));

        $_REQUEST['controller'] = 'aa';
        $_REQUEST['action'] = 'bb';
        $this->assertFalse($this->_isAccessible->invokeArgs($this->_acl, array($rule)));
        $this->_acl->setRole('anonymous');
        $this->_acl->check();
        $this->assertEquals('auth', $_REQUEST['controller']);
        $this->assertEquals('login', $_REQUEST['action']);


        $_REQUEST['controller'] = 'auth';
        $_REQUEST['action'] = 'login';
        $this->assertTrue($this->_isAccessible->invokeArgs($this->_acl, array($rule)));

    }

}
