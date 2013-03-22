<?php
    chdir('../../');    
    include './main.php';
    
    class EpiCollectWebAppRootTest extends PHPUnit_Framework_TestCase
    {
        protected function setup()
        {
            parent::setup();
            
            $_SERVER['HTTP_HOST'] = 'test.epicollect.net';
            $_SERVER['DOCUMENT_ROOT'] = 'C:/inetpub/wwwroot';
            $_SERVER['SCRIPT_FILENAME'] = 'C:/inetpub/wwwroot/main.php';
            $_SERVER['PHP_SELF'] = '/main.php';
            
            $this->app = new EpiCollectWebApp();
            $this->app->before_first_request();    
            $this->app->before_request();         
            ob_start();
        }

        protected function tearDown()
        {
            header_remove();
            parent::tearDown();
        }

        public function test_vars()
        {
            $this->assertEquals('1.4', EpiCollectWebApp::CODE_VERSION);
            $this->assertEquals(1.0, EpiCollectWebApp::XML_VERSION);
        }
        // DOES NOT WORK FROM CLI SAPI!
       /* public function test_do_not_cache()
        {
            
            EpiCollectWebApp::DoNotCache();
                        
            $headers_list = headers_list();
            
            $this->assertNotEmpty($headers_list);
            $this->assertContains('Cache-Control: no-cache, must-revalidate', $headers_list);
            
        }*/
        
        public function test_get_url()
        {
            $_SERVER['REQUEST_URI'] = '/';
            $this->assertEquals('', $this->app->get_request_url());
            
            $_SERVER['REQUEST_URI'] = '/createProject';
            $this->assertEquals('createProject', $this->app->get_request_url());
        }
        
        
        public function test_IIS_get_url_in_folder()
        {
            $_SERVER['PHP_SELF'] = '/epicollectplus/main.php';
            $_SERVER['SCRIPT_NAME'] = '/epicollectplus/main.php';
            
            $app2 = new EpiCollectWebApp();
            $app2->before_first_request();    
            $app2->before_request();     
            $_SERVER['REQUEST_URI'] = '/epicollectplus/';
            $this->assertEquals('', $app2->get_request_url());
            
            $_SERVER['REQUEST_URI'] = '/epicollectplus/createProject';
            $this->assertEquals('createProject', $app2->get_request_url());
//            $this->assertEquals('createProject', $app2->get_request_url());
        }
        
        public function test_Apache_get_url_in_folder()
        {   
            $_SERVER['DOCUMENT_ROOT'] = 'C:/inetpub/wwwroot';
            $_SERVER['SCRIPT_FILENAME'] = 'C:/inetpub/wwwroot/epicollectplus/main.php';
            $_SERVER['PHP_SELF'] = '/epicollectplus/main.php';
      
            $app2 = new EpiCollectWebApp();
            $app2->before_first_request();    
            $app2->before_request();     
            $_SERVER['REQUEST_URI'] = '/epicollectplus/';
            $this->assertEquals('', $app2->get_request_url());
            
            $_SERVER['REQUEST_URI'] = '/epicollectplus/createProject';
            $this->assertEquals('createProject', $app2->get_request_url());
            
            $_SERVER['REQUEST_URI'] = '/epicollectplus/project/form';
            $this->assertEquals('project/form', $app2->get_request_url());
        }
        
        public function test_make_url()
        {
            $_SERVER['DOCUMENT_ROOT'] = 'C:/inetpub/wwwroot';
            $_SERVER['SCRIPT_FILENAME'] = 'C:/inetpub/wwwroot/main.php';
            $_SERVER['PHP_SELF'] = '/main.php';
      
            $app2 = new EpiCollectWebApp();
            $app2->before_first_request();    
            $app2->before_request(); 
            $fn = 'abc.xml';
            $_SERVER['HTTP_HOST'] = 'www.domain.tld';
            $this->assertEquals('http://www.domain.tld/ec/uploads/abc.xml', $app2->makeUrl($fn));
        }
        
        public function testIndex()
        {
            $_SERVER['REQUEST_URI'] = '/';
            $_SESSION = array();
            
            $this->app->process_request();
        }
    }
    
?>
