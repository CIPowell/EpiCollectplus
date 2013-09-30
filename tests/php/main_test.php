<?php
include('../../EpiCollect.php');

class CFG_Tests extends PHPUnit_Framework_TestCase
{
	protected function setup()
	{
		
	}
	
	protected function tearDown()
	{
		
	}
	
	// test CFG setup
	public function test_cfg_open()
	{
		$app = new EpiCollectWebApp();
		$app->openCfg();
		$this->assertNotEqual($app->cfg, false);
	}
	
	public function test_cfg_open_create()
	{
		// delete config file
		$cfg = new ConfigManager('../../ec/epicollect.ini');
	}
	
	public function test_cfg_write()
	{
		
	}
	
}
?>