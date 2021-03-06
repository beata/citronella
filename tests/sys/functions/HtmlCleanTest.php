<?php
require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../../source/sys/functions.php';
require_once __DIR__ . '/../../../source/sys/core.php';
require_once __DIR__ . '/../../config/config.php';

App::$id = 'admin';

class Sys_Functions_HtmlCleanTest extends PHPUnit_Framework_TestCase
{
    public function testAttr_EnableID()
    {
        $string = '<a id="anchor" href="http://example.com">Text</a>';
        $this->assertEquals('<a id="anchor" href="http://example.com">Text</a>', HtmlClean($string, 'admin'));
    }

    public function testAnchorId()
    {
        $string = '<a id="anchor">Text</a>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));

    }

    public function testAnchorName()
    {
        $string = '<a name="anchor">Text</a>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }

    public function testAnchorLink()
    {
        $string = '<a href="#anchor">Text</a>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }

    public function testLink()
    {
        $string = '<a href="http://aaaa">Text</a>';
        $this->assertEquals('<a href="http://aaaa">Text</a>', HtmlClean($string, 'admin'));
    }

    public function testLinkTarget()
    {
        $string = '<a href="http://aaaa" target="_blank">Text</a>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }

    public function testLinkHost()
    {
        $string = '<a href="aaaa.txt">Text</a>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }

    public function testFont()
    {
        $string = '<font style="color:#FF0000;">Font</font>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));

        $string = '<font face="標楷體" size="5" color="#FF0000">Font</font>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }

    public function testAll()
    {
        $string = '<p>
    <strong>康委員</strong>，
<em>那是毫</em>無
<u>問題的</u>；
<strike>但是</strike>，
<sub>我們</sub>是不
<sup>鼓勵</sup>的，這
<span style="background-color:#ff8c00;">個承諾</span>百分
<span style="color:#ffd700;">之百兌</span>現，現
<span style="font-size:16px;">在講出</span>來的，利
<span style="font-family:\'lucida sans unicode\', \'lucida grande\', sans-serif;">比亞少數</span>僑民都撤掉了，</p>
<h3>
    我就要負責補足，省錢又有市場</h3>
<p>
    <tt>，我們一定</tt>充分支持</p>
<h3 style="color:#FF0000;">
    且提供原民會及受災</h3>
<ol><li>
        的縣、鄉足夠的資</li>
</ol><ul><li>
        源，大體上都會截長補短</li>
</ul><blockquote>
    <p>這裡面有兩個因素</p>
</blockquote>';

        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }

    public function testUnderline()
    {
        $string = '<u>Text</u>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }
    public function testPagebreak()
    {
        $string = '<div style="page-break-after:always;"><span style="display:none;"></span></div>';
        $this->assertEquals($string, HtmlClean($string, 'admin'));
    }
}
