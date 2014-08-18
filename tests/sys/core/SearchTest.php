<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../../source/sys/functions.php';
require_once __DIR__ . '/../../../source/sys/core.php';
require_once __DIR__ . '/../../config/config.php';

class Sys_Core_SearchTest extends PHPUnit_Framework_TestCase
{

   public function testConstruct()
   {
       $search = new Search;

       $this->assertObjectHasAttribute('where', $search);
       $this->assertObjectHasAttribute('params', $search);
       $this->assertTrue(is_array($search->where));
       $this->assertTrue(is_array($search->params));
   }

   public function testSqlWhere()
   {
       $search = new Search;

       $this->assertEquals('', $search->sqlWhere());

       $search->where[] = '`col1` = ?';
       $search->where[':b'] = '`col2` = ?';

       $this->assertEquals(' WHERE `col1` = ? AND `col2` = ?', $search->sqlWhere());
   }
}
