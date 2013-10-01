<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../../source/sys/functions.php';
require_once __DIR__ . '/../../../source/sys/core.php';
require_once __DIR__ . '/../../config/config.php';

class TestController extends BaseController {}
class TestModule extends Module {}

class Sys_Core_ModuleTest extends PHPUnit_Framework_TestCase
{

   public function testConstructAssignsController()
   {
       $controller = new TestController;
       $module = new TestModule($controller);

       $mC = PHPUnit_Framework_Assert::readAttribute($module, 'c');

       $this->assertObjectHasAttribute('c', $module);
       $this->assertSame($controller, $mC);
   }

   public function testConstructAssignsMembers()
   {
       $controller = new TestController;
       $module = new TestModule($controller, array(
           'm1' => 1,
           'm2' => 2
       ));

       $this->assertObjectHasAttribute('m1', $module);
       $this->assertObjectHasAttribute('m2', $module);
       $this->assertEquals(1, $module->m1);
       $this->assertEquals(2, $module->m2);
   }

}
