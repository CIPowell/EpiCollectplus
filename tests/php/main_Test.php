<?php
    chdir('../../');    
    include "./main.php";



class EpiCollectUtilsTest extends PHPUnit_Framework_TestCase
{
    protected function setup()
    {
        parent::setup();
        ob_start();
    }

    protected function tearDown()
    {
        if(!headers_sent()) header_remove();
        parent::tearDown();
    }

    public function test_get_val()
    {
        $arr = array();
        $this->isNull(EpiCollectUtils::array_get_if_exists($arr, 'foo'));

        $arr["foo"] = "bar";
        $this->assertEquals(EpiCollectUtils::array_get_if_exists($arr, 'foo'), 'bar');
    }
    
    public function test_gen_string()
    {
        $str22 = EpiCollectUtils::genStr();
        $this->assertEquals(strlen($str22), 22);
        $this->assertRegExp('/^[a-z0-9\.\/]+$/i', $str22);
        
        $str1 = EpiCollectUtils::genStr(1);
        $this->assertEquals(strlen($str1), 1);
        $this->assertRegExp('/^[a-z0-9\.\/]+$/i', $str1);
        
        $str50 = EpiCollectUtils::genStr(50);
        $this->assertEquals(strlen($str50), 50);
        $this->assertRegExp('/^[a-z0-9\.\/]+$/i', $str50);        
        
        $str50 = EpiCollectUtils::genStr(128);
        $this->assertEquals(strlen($str50), 128);
        $this->assertRegExp('/^[a-z0-9\.\/]+$/i', $str50);        
        
        $str50 = EpiCollectUtils::genStr(1024);
        $this->assertEquals(strlen($str50), 1024);
        $this->assertRegExp('/^[a-z0-9\.\/]+$/i', $str50);        
    }
    
    public function test_escape_csv()
    {
        $str = 'abcd';
        $this->assertEquals($str, EpiCollectUtils::escapeTSV($str));
        
        $str = "ab\tcd";
        $this->assertEquals("ab\\tcd", EpiCollectUtils::escapeTSV($str));
        $this->assertEquals('ab\tcd', EpiCollectUtils::escapeTSV($str));
        
        $str = "ab\ncd";
        $this->assertEquals("ab\\ncd", EpiCollectUtils::escapeTSV($str));
        $this->assertEquals('ab\ncd', EpiCollectUtils::escapeTSV($str));
        
        $str = "ab\rcd";
        $this->assertEquals("ab\\rcd", EpiCollectUtils::escapeTSV($str));
        $this->assertEquals('ab\rcd', EpiCollectUtils::escapeTSV($str));
    }
}


?>