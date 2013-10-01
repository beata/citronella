<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../../source/sys/functions.php';
require_once __DIR__ . '/../../../source/sys/core.php';
require_once __DIR__ . '/../../config/config.php';

class Sys_Core_DBHelperTest extends PHPUnit_Framework_TestCase
{
    public function testClearNewFiles()
    {
        $tmpfile = __DIR__ . '/../../tmp/tmpfile';
        exec('touch ' . $tmpfile);
        $this->assertTrue(file_exists($tmpfile));

        $data = new stdclass;
        $data->_new_files = array( $tmpfile);

        DBHelper::clearNewFiles($data);

        $this->assertFalse(file_exists($tmpfile));
    }

    public function testIn()
    {
        $data = array();
        $this->assertNull(DBHelper::in($data));

        $data = array(
            'a' => 'A',     // string
            "'" => "''",    // single quote
            '"' => '""',    // double quote
            '`' => "``",    // `
            1, 2, 3         // number
        );
        $this->assertEquals(" IN ("
            . "'A',"
            . "'\\'\\'',"
            . "'\\\"\\\"',"
            . "'``',"
            . "'1','2','3'"
            . ")",
            DBHelper::in($data));

        $data = array(1,2,3);
        $this->assertEquals("col IN ('1','2','3')",
            DBHelper::in($data, 'col'));
    }

    public function testWhere()
    {
        $data = array();
        $this->assertEquals('',
            DBHelper::where($data));

        $data = array(
            'col1' => 'value1',
            'col2' => '"value2"',
            'col3' => "'value3'",
        );

        $this->assertEquals("`col1` = 'value1'"
            . " AND `col2` = '\\\"value2\\\"'"
            . " AND `col3` = '\'value3\''",
            DBHelper::where($data));
    }

    public function testOrWhere()
    {
        $data = array();
        $this->assertEquals('1=1',
            DBHelper::orWhere($data));

        $data = array(
            'col1' => 'value1',
            'col2' => '"value2"',
            'col3' => "'value3'"
        );
        $this->assertEquals("(`col1` = 'value1'"
            . " OR `col2` = '\\\"value2\\\"'"
            . " OR `col3` = '\'value3\'')",
            DBHelper::orWhere($data));

        $data = array(
            'col1' => '1',
            'col2' => '2',
            'col3' => "3",
            array(
                'col4' => '4',
                'col5' => '5',
                'col6' => "6",
            )
        );

        $this->assertEquals("(`col1` = '1' OR `col2` = '2' OR `col3` = '3'"
            . " OR (`col4` = '4' AND `col5` = '5' AND `col6` = '6')"
            . ")",
            DBHelper::orWhere($data));
    }

    public function testToSetSql()
    {
        $fields = array( 'col1', 'col2', 'col3');

        $data = new stdclass;
        $data->col1 = 1;
        $data->col2 = 2;
        $data->col3 = 3;
        $params = array();

        $this->assertEquals('`col1` = ?,'
            . '`col2` = ?,'
            . '`col3` = ?',
            DBHelper::toSetSql($data, $fields, $params));

        $this->assertEquals(array($data->col1, $data->col2, $data->col3), $params);

        // test rawValueFields
        $rawValueFields = array('time' => 'NOW()', 'num' => 1);
        $params = array();
        $this->assertEquals('`col1` = ?,'
            . '`col2` = ?,'
            . '`col3` = ?,'
            . '`time` = NOW(),'
            . '`num` = 1',
            DBHelper::toSetSql($data, $fields, $params, $rawValueFields));
        $this->assertEquals(array($data->col1, $data->col2, $data->col3), $params);
    }

    public function testToKeySetSql()
    {
        $fields = array( 'col1', 'col2', 'col3');

        $data = new stdclass;
        $data->col1 = 1;
        $data->col2 = 2;
        $data->col3 = 3;
        $params = array();

        $this->assertEquals('`col1` = :col1,'
            . '`col2` = :col2,'
            . '`col3` = :col3',
            DBHelper::toKeySetSql($data, $fields, $params));

        $this->assertEquals(array(
            ':col1' => $data->col1,
            ':col2' => $data->col2,
            ':col3' => $data->col3), $params);

        // test rawValueFields
        $rawValueFields = array('time' => 'NOW()', 'num' => 1);
        $params = array();
        $this->assertEquals('`col1` = :col1,'
            . '`col2` = :col2,'
            . '`col3` = :col3,'
            . '`time` = NOW(),'
            . '`num` = 1',
            DBHelper::toKeySetSql($data, $fields, $params, $rawValueFields));
        $this->assertEquals(array(
            ':col1' => $data->col1,
            ':col2' => $data->col2,
            ':col3' => $data->col3), $params);
    }

    public function testToInsertSql()
    {
        $fields = array( 'col1', 'col2', 'col3');

        $data = new stdclass;
        $data->col1 = 1;
        $data->col2 = 2;
        $data->col3 = 3;
        $params = array();

        $this->assertEquals('(`col1`,`col2`,`col3`) VALUES (?,?,?)',
            DBHelper::toInsertSql($data, $fields, $params));

        $this->assertEquals(array($data->col1, $data->col2, $data->col3), $params);

        // test rawValueFields
        $rawValueFields = array('time' => 'NOW()', 'num' => 1);
        $params = array();
        $this->assertEquals('(`col1`,`col2`,`col3`,`time`,`num`) VALUES (?,?,?,NOW(),1)',
            DBHelper::toInsertSql($data, $fields, $params, $rawValueFields));
        $this->assertEquals(array($data->col1, $data->col2, $data->col3), $params);
    }

    public function testToKeyInsertSql()
    {
        $fields = array( 'col1', 'col2', 'col3');

        $data = new stdclass;
        $data->col1 = 1;
        $data->col2 = 2;
        $data->col3 = 3;
        $params = array();

        $this->assertEquals('(`col1`,`col2`,`col3`) VALUES (:col1,:col2,:col3)',
            DBHelper::toKeyInsertSql($data, $fields, $params));

        $this->assertEquals(array(
            ':col1' => $data->col1,
            ':col2' => $data->col2,
            ':col3' => $data->col3), $params);

        // test rawValueFields
        $rawValueFields = array('time' => 'NOW()', 'num' => 1);
        $params = array();
        $this->assertEquals('(`col1`,`col2`,`col3`,`time`,`num`) VALUES (:col1,:col2,:col3,NOW(),1)',
            DBHelper::toKeyInsertSql($data, $fields, $params, $rawValueFields));
        $this->assertEquals(array(
            ':col1' => $data->col1,
            ':col2' => $data->col2,
            ':col3' => $data->col3), $params);
    }

    public function testSplitCommaList()
    {
        $data = array(1,2,3);
        $this->assertEquals($data, DBHelper::splitCommaList($data));

        $data = array();
        $this->assertEquals(array(), DBHelper::splitCommaList($data));

        $data = '';
        $this->assertEquals(array(), DBHelper::splitCommaList($data));

        $data = '1';
        $this->assertEquals(array('1' => 1), DBHelper::splitCommaList($data));

        $data = '1,2,3';
        $this->assertEquals(array('1'=>1,'2'=>2,'3'=>3), DBHelper::splitCommaList($data));
    }
}
