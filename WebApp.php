<?php

class EpiCollectWebApp 
{
    /**
     *
     * @var string the URL of the homepage of this site, included in case EpiCollect+ is deployed in a sub-folder of a website 
     */
    public $site_root = '';
    private $auth, $cfg, $db, $logger, $host, $https_enabled;
    
    public $base_url;
    
    const XML_VERSION = 1.0;
    const CODE_VERSION = "1.4";
    
    private $page_rules = array();
    
    function __construct() {
        date_default_timezone_set('UTC');
        
        $script_name = EpiCollectUtils::array_get_if_exists($_SERVER, 'SCRIPT_NAME');
        
        if( $script_name !== false )
        {
            if( strpos($script_name, 'main.php') )
            {
                    //IIS
                    $this->site_root = str_replace('/main.php', '', $_SERVER['PHP_SELF']);
            }
            else
            {
                    //Apache
                    $this->site_root = str_replace(array($_SERVER['DOCUMENT_ROOT'], '/main.php') , '', $_SERVER['SCRIPT_FILENAME']);
            }
        }
        
        $this->host = EpiCollectUtils::array_get_if_exists($_SERVER, 'HTTP_HOST');
        
       
        
        if($this->site_root === '')
        {
            $this->base_url = sprintf('http://%s', $this->host);
        }
        else
        {
             $this->base_url = sprintf('http://%s%s', $this->host, $this->site_root);
        }
               
        $this->cfg = new ConfigManager('./ec/epicollect.ini');
        
        
        $db_cfg = $this->cfg->settings['database'];
        $this->db = new EpiCollectDatabaseConnection('mysql', $db_cfg['server'], $db_cfg['database'], $db_cfg['user'] , $db_cfg['password'], $db_cfg['port']);
        $this->logger = new Logger('Ec2', $this->db);
        $this->auth = new AuthManager($this, $this->cfg);
        
        try{
            $hasManagers = $this->db->connected && count($this->auth->getServerManagers()) > 0;
        }
        catch (Exception $err)
        {
            $hasManagers = false;
            $this->logger->write('error', $err->getMessage());
        }

        $this->page_rules = array(
            'markers/point' => new PageRule(null, 'getPointMarker'),
            'markers/cluster' => new PageRule(null, 'getClusterMarker'),
            //static file handlers
            '' => new PageRule('index.html', 'siteHome'),
            'index.html?' => new PageRule('index.html', 'siteHome'),
            'privacy.html' => new PageRule('privacy.html', 'defaultHandler'),
            '[a-zA-Z0-1]+\.html' => new PageRule(null, 'defaultHandler'),
            'images/.+' => new PageRule(),
            'favicon\..+' => new PageRule(),
            'js/.+' => new PageRule(),
            'css/.+' => new PageRule(),
            'EpiCollectplus\.apk' => new PageRule(),
            'html/projectIFrame.html' => new PageRule(),

            //project handlers
            'pc' => new PageRule(null, 'projectCreator', true),
            'create' => new PageRule(null, 'createFromXml', true),
            'createProject.html' => new PageRule(null, 'createProject', true),
            'projectHome.html' => new PageRule(null, 'projectHome'),
            'createOrEditForm.html' => new PageRule(null ,'formBuilder', true),
            'uploadProject' =>new PageRule(null, 'uploadProjectXML', true),
            'getForm' => new PageRule(null, 'getXML',	 true),
            'validate' => new PageRule(null, 'validate',false),

            //login handlers
            //'Auth/loginCallback.php' => new PageRule(null,'loginCallbackHandler'),
            'login' => new PageRule(null,'loginHandler', false, true),
            'loginCallback' => new PageRule(null,'loginCallback', false, true),
            'logout' => new PageRule(null, 'logoutHandler'),
            'chooseProvider.html' => new PageRule(null, 'chooseProvider'),

             //user handlers
            'updateUser.html' => new PageRule(null, 'updateUser', true),
            'saveUser' =>new PageRule(null, 'saveUser', true),
            'user/manager/?' => new PageRule(null, 'managerHandler', true),
            'user/.*@.*?' => new PageRule(null, 'userHandler', true),
            'admin' => new PageRule(null, 'admin', true),
            'listUsers' => new PageRule(null, 'listUsers', true),
            'disableUser' => new PageRule(null, 'disableUser',true),
            'enableUser' => new PageRule(null, 'enableUser',true),
            'resetPassword' => new PageRule(null, 'resetPassword',true),
            'register' => new PageRule(null, 'createAccount', false),

            //generic, dynamic handlers
            'getControls' =>  new PageRule(null, 'getControlTypes'),
            'uploadFile.php' => new PageRule(null, 'uploadHandlerFromExt'),
            'ec/uploads/.+\.(jpe?g|mp4)$' => new PageRule(null, 'getMedia'),
            'ec/uploads/.+' => new PageRule(null, null),

            'uploadTest.html' => new PageRule(null, 'defaultHandler', true),
            'test' => new PageRule(null, 'siteTest', false),
            'tests.*' => new PageRule(),
            'createDB' => new PageRule(null, 'setupDB',true),
            'writeSettings' => new PageRule(null, 'writeSettings', true),

            //to API
            'projects' => new PageRule(null, 'projectList'),
            '[a-zA-Z0-9_-]+(\.xml|\.json|\.tsv|\.csv|/)?' =>new PageRule(null, 'projectHome'),
            '[a-zA-Z0-9_-]+/upload' =>new PageRule(null, 'uploadData'),
            '[a-zA-Z0-9_-]+/download' =>new PageRule(null, 'downloadData'),
            '[a-zA-Z0-9_-]+/summary' =>new PageRule(null, 'projectSummary'),
            '[a-zA-Z0-9_-]+/usage' =>  new PageRule(null, 'projectUsage'),
            '[a-zA-Z0-9_-]+/formBuilder(\.html)?' =>  new PageRule(null, 'formBuilder', true),
            '[a-zA-Z0-9_-]+/editProject.html' =>new PageRule(null, 'editProject', true),
            '[a-zA-Z0-9_-]+/update' =>new PageRule(null, 'updateProject', true),
            '[a-zA-Z0-9_-]+/manage' =>new PageRule(null, 'updateProject', true),
            '[a-zA-Z0-9_-]+/updateStructure' =>new PageRule(null, 'updateXML', true),
            '[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+/__stats' =>new PageRule(null, 'tableStats'),
            '[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+/__activity' =>new PageRule(null, 'formDataLastUpdated'),
            '[a-zA-Z0-9_-]+/uploadMedia' =>new PageRule(null, 'uploadMedia'),
            '[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+/uploadMedia' =>new PageRule(null, 'uploadMedia'),
            '[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+/__getImage' =>new PageRule(null, 'getImage'),

            '[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+(\.xml|\.json|\.tsv|\.csv|\.kml|\.js|\.css|/)?' => new PageRule(null, 'formHandler'),

            //'[a-zA-Z0-9_-]*/[a-zA-Z0-9_-]*/usage' => new  => new PageRule(null, formUsage),
            '[^/\.]*/[^/\.]+/[^/\.]*(\.xml|\.json|/)?' => new PageRule(null, 'entryHandler')

            //forTesting

        );
    }
    
    function before_first_request()
    {
        if($this->cfg->settings['security']['use_ldap'] && !function_exists('ldap_connect'))
        {
            $this->cfg->settings['security']['use_ldap'] = false;
            $this->cfg->writeConfig();
        }


        if(!array_key_exists('salt',$this->cfg->settings['security']) || trim($this->cfg->settings['security']['salt']) == '')
        {
            $str = EpiCollectUtils::genStr();
            $this->cfg->settings['security']['salt'] = $str;
            $this->cfg->writeConfig();
        }

    }
    
    function before_request() 
    {
        // For Security.
        if (isset($_REQUEST['_SESSION']))
        {
            EpiCollectWebApp::BadRequest();
            die();
        }
       
        @session_start();
        
        if(!EpiCollectUtils::array_get_if_exists($_SESSION, 'SEEN_COOKIE_MSG')) {
            EpiCollectWebApp::flash(sprintf('EpiCollectPlus uses cookies make the site work. If you are concerned about our use of cookies please read our <a href="%s/privacy.html">Privacy Statement</a>', $this->base_url));
            $_SESSION['SEEN_COOKIE_MSG'] = true;
        }
    } 
    
    function getDB()
    {
        return $this->db;
    }
    
    function process_request()
    {
        $url = $this->get_request_url();
        $rule = '';
        
        if(array_key_exists($url, $this->page_rules))
        {
                $rule = $this->page_rules[$url];
        }
        else
        {

                foreach(array_keys($this->page_rules) as $key)
                {
                        if(preg_match("/^".EpiCollectUtils::regexEscape($key)."$/", $url))
                        {
                                //echo $key;
                                $rule = $this->page_rules[$key];
                                break;
                        }
                }
        }

        if($rule !== '')
        {
                if($rule->secure && !EpiCollectUtils::array_get_if_exists($_SERVER, "HTTPS"))
                {
                        $this->https_enabled = false;
                        try{
                                $this->https_enabled = file_exists("https://{$_SERVER["HTTP_HOST"]}/{$this->site_root}/{$url}");
                        }
                        catch(Exception $e)
                        {
                                $this->https_enabled = false;
                        }
                        if($this->https_enabled)
                        {
                            EpiCollectWebApp::Redirect(sprintf("https://%s/%s/%s",$_SERVER["HTTP_HOST"], $this->site_root, $url));
                                die();
                        }
                }
                elseif($rule->secure)
                {
                        //EpiCollectWebApp::EpiCollectWebApp::flash("Warning: this page is not secure as HTTPS is not avaiable", "err");
                }


                if($rule->login && !$this->auth->isLoggedIn())
                {
                    EpiCollectWebApp::DoNotCache();

                        if(array_key_exists("provider", $_GET))
                        {
                                $_SESSION["provider"] = $_GET["provider"];
                              
                                $frm = $this->auth->requestlogin($url, $_GET["provider"]);
                        }
                        else
                        {
                                $frm = $this->auth->requestlogin($url);
                        }
                        echo $this->applyTemplate("./base.html", "./loginbase.html", array( "form" => $frm));
                        return;
                }
                if($rule->redirect)
                {
                        $url = $rule->redirect;
                }
                if($rule->handler)
                {
                        $h = $rule->handler;
                        //if($h != 'defaultHandler') @session_start();
                        echo $this->$h();
                }
                else
                {

                        //static files
                        EpiCollectWebApp::ContentType($url);
                        EpiCollectWebApp::CacheFor(100000);
                        echo file_get_contents("./" . $url);
                }
        }
        else
        {

                echo $this->applyTemplate("./base.html", "./error.html");
        }
    }
    
    function applyTemplate($baseUri, $targetUri = false, $templateVars = array())
    {
            $template = file_get_contents(sprintf('./html/%s', trim( $baseUri,'.')));
            $templateVars['SITE_ROOT'] = $this->site_root;
            $templateVars['uid'] = md5($this->host);
            $templateVars['codeVersion'] = EpiCollectWebApp::CODE_VERSION;
            $templateVars['protocol'] = (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http');
            $templateVars['GA_ACCOUNT'] = $this->cfg->settings['misc']['ga_account'];
            // Is there a user logged in?

            $flashes = '';

            if(array_key_exists('flashes', $_SESSION) && is_array($_SESSION['flashes']))
            {
                    while($flash = array_pop($_SESSION['flashes']))
                    {
                            $flashes .= sprintf('<p class="flash %s">%s</p>', $flash["type"], $flash["msg"]);
                    }
            }


            try{
                    if( $this->db->connected && $this->auth && $this->auth->isLoggedIn())
                    {

                            //if so put the user's name and a logout option in the login section
                            if($this->auth->isServerManager())
                            {
                                    $template = str_replace('{#loggedIn#}', 'Logged in as ' . $this->auth->getUserNickname() . ' (' . $this->auth->getUserEmail() .  ')  <a href="{#SITE_ROOT#}/logout">Sign out</a>  <a href="{#SITE_ROOT#}/updateUser.html">Update User</a>  <a href="{#SITE_ROOT#}/admin">Manage Server</a>', $template);
                            }
                            else
                            {
                                    $template = str_replace('{#loggedIn#}', sprintf('Logged in as %s (%s) <a class="btn btn-mini" href="{#SITE_ROOT#}/logout">Sign out</a>  <a href="{#SITE_ROOT#}/updateUser.html">Update User</a>', $this->auth->getUserNickname(), $this->auth->getUserEmail()), $template);
                            }
                            $templateVars['userEmail'] = $this->auth->getUserEmail();
                    }
                    // else show the login link
                    else
                    {
                            $template = str_replace('{#loggedIn#}', '<a href="{#SITE_ROOT#}/login">Sign in</a>', $template);
                    }
                    // work out breadcrumbs
                    //$template = str_replace("{#breadcrumbs#}", '', $template);
            }catch(Exception $err){
                    siteTest();
            }	

            $script = "";
            $sections = array();
            if($targetUri)
            {

                    $fname = sprintf('./html/%s', trim( $targetUri,'./'));
                    if(file_exists($fname))
                    {
                            $data = file_get_contents($fname);

                            $fPos = 0;
                            $iStart = 0;
                            $iEnd = 0;
                            $sEnd = 0;
                            $id = '';

                            while($fPos <= strlen($data) && $fPos >= 0)
                            {
                                    //echo "--";
                                    // find {{
                                    $iStart = strpos($data, '{{', $fPos);

                                    if($iStart===false || $iStart < $fPos) break;
                                    //echo $iStart;
                                    //get identifier (to }})
                                    $iEnd = strpos($data, '}}', $iStart);

                                    //echo $iEnd;
                                    $id = substr($data, $iStart + 2, ($iEnd-2) - ($iStart));
                                    //find matching end {{/}}
                                    $sEnd = strpos($data, sprintf('{{/%s}}', $id), $iEnd);
                                    $sections[$id] = substr($data, $iEnd + 2, $sEnd - ($iEnd + 2));

                                    $fPos = $sEnd + strlen($id) + 3;
                                    //echo ("$fPos --- " . strlen($data) . " $id :: ");
                            }
                    }
                    else
                    {
                            //$sections['script'] = '';
                            //$sections['main'] = '<h1>404 - page not found</h1>
                            //	<p>Sorry, the page you were looking for could not be found.</p>';
                            EpiCollectWebApp::NotFound("The Page you were looking for ");
                    }
                    foreach(array_keys($sections) as $sec)
                    {
                            // do processing
                            $template = str_replace(sprintf('{#%s#}',$sec) , $sections[$sec], $template);
                    }
                    $template = str_replace('{#flashes#}', $flashes, $template);
            }
            if($templateVars)
            {
                    foreach($templateVars as $sec => $cts)
                    {
                            // do processing
                            $template = str_replace(sprintf('{#%s#}', $sec), $cts, $template);
                    }
            }

            $template = preg_replace('/\{#[a-z0-9_]+#\}/i', '', $template);
            return $template;
    }
    
     // Handlers
    
    /**
     * The site landing page
     * @return string
     */
    function siteHome()
    {
        EpiCollectWebApp::DoNotCache(); // There are 
        
            $vals = array();
            
            if(!$this->db->connected)
            {
                    $rurl = sprintf('%s/test?redir=true', $this->base_url);
                    EpiCollectWebApp::Redirect($rurl);
                    return;
            }

            $res = $this->db->do_query("SELECT name, ttl, ttl24 FROM (SELECT name, count(entry.idEntry) as ttl, x.ttl as ttl24 FROM project left join entry on project.name = entry.projectName left join (select count(idEntry) as ttl, projectName from entry where created > ((UNIX_TIMESTAMP() - 86400)*1000) group by projectName) x on project.name = x.projectName Where project.isListed = 1 group by project.name) a order by ttl desc LIMIT 10");
            if($res !== true)
            {

                    
                    $rurl = sprintf('%s/test?redir=true', $this->base_url);
                    EpiCollectWebApp::Redirect($rurl);
                    return;
            }
            $vals["projects"] = "<div class=\"ecplus-projectlist\"><h1>Most popular projects on this server</h1>" ;

            $i = 0;

            while($row = $this->db->get_row_array())
            {
                    $vals["projects"] .= "<div class=\"project\"><a href=\"{#SITE_ROOT#}/{$row["name"]}\">{$row["name"]}</a><div class=\"total\">{$row["ttl"]} entries with <b>" . ($row["ttl24"] ? $row["ttl24"] : "0") ."</b> in the last 24 hours </div></div>";
                    $i++;
            }

            if($i == 0)
            {
                    $vals["projects"] = "<p>No projects exist on this server, <a href=\"createProject.html\">create a new project</a></p>";
            }
            else
            {
                    $vals["projects"] .= "</div>";
            }

            if($this->auth->isLoggedIn())
            {
                    $vals['userprojects'] = '<div class="ecplus-userprojects"><h1>My Projects</h1>';

                    $prjs = EcProject::getUserProjects($this->db, $this->auth->getEcUserId());
                    $count = count($prjs);

                    for($i = 0; $i < $count; $i++)
                    {
                            $vals['userprojects'] .= "<div class=\"project\"><a href=\"{#SITE_ROOT#}/{$prjs[$i]["name"]}\">{$prjs[$i]["name"]}</a><div class=\"total\">{$prjs[$i]["ttl"]} entries with <b>" . ($prjs[$i]["ttl24"] ? $prjs[$i]["ttl24"] : "0") ."</b> in the last 24 hours </div></div>";
                    }

                    $vals['userprojects'] .= '</div>';
            }

            return $this->applyTemplate("base.html","index.html",$vals);
    }
    
    /**
     * Called when the page requires a log in
     */
    function loginHandler()
    {
	$cb_url='';
	EpiCollectWebApp::DoNotCache();
	
        $url = $this->get_request_url();
        
	if( !preg_match('/login.php/', $url) )
	{
		$cb_url = $url; 
	}
	
	if(array_key_exists('provider', $_GET))
	{
		$_SESSION['provider'] = $_GET['provider'];
		$frm = $this->auth->requestlogin($cb_url, $_SESSION['provider']);
	}
	elseif (array_key_exists('provider', $_SESSION))
	{
		$frm = $this->auth->requestlogin($cb_url, $_SESSION['provider']);
	}
	else
	{
		$frm = $this->auth->requestlogin($cb_url);
	}

	
	return $this->applyTemplate('./base.html', './loginbase.html', array( 'form' => $frm));
    }
    
    function loginCallback()
    {
        EpiCollectWebApp::DoNotCache();
     
        $provider = EpiCollectUtils::array_get_if_exists($_POST, 'provider');
        if(!$provider)
            $provider = EpiCollectUtils::array_get_if_exists($_SESSION, 'provider');
        else {
            $_SESSION['provider'] = $provider;
        }

	$this->auth->callback($provider);
    }
    
    function logoutHandler()
    {
        EpiCollectWebApp::DoNotCache();
        if($this->auth)
        {
                $this->auth->logout();
                EpiCollectWebApp::Redirect($this->base_url);
                return;
        }
        else
        {
                echo 'No Auth';
        }
}
    
    /**
    * Produce a list of all the projects on this server that are
    * 	- publically listed
    *  - if a user is logged in, owned, curated or managed by the user
    */
    function projectList()
    {
           
            $prjs = EcProject::getPublicProjects($this->db);
            $usr_prjs = array();
            if($this->auth->isLoggedIn())
            {  
                $usr_prjs = EcProject::getUserProjects($this->db, $this->auth->getEcUserId());
                $up_l = count($usr_prjs);
                for($p = 0; $p < $up_l; $p++ )
                {
                    if($usr_prjs[$p]["listed"] === 0)
                    {
                        array_push($prjs, $usr_prjs[$p]);
                    }
                }
            }

            return json_encode($prjs);
    }
    
    /**
     * The create project screen.
     * @return String rendered page
     */
    function createProject()
    {
	EpiCollectWebApp::DoNotCache();
	return $this->applyTemplate("./base.html","./createProject.html");
    }
    
    function createFromXml()
    {
        $prj = new EcProject();

        if(array_key_exists("xml", $_REQUEST) && $_REQUEST["xml"] != "")
        {
                $xmlFn = "ec/xml/{$_REQUEST["xml"]}";

                $prj->parse(file_get_contents($xmlFn));
        }
        elseif(array_key_exists("name", $_POST))
        {
                $prj->name = $_POST["name"];
                $prj->submission_id = strtolower($prj->name);
        }
        elseif(array_key_exists("raw_xml", $_POST))
        {
                $prj->parse($_POST["raw_xml"]);
        }

        if(!$prj->name || $prj->name == "")
        {
                EpiCollectWebApp::flash("No project name provided");
                EpiCollectWebApp::Redirect(sprintf('%s/createProject.html', $this->base_url));
        }

        if( $_REQUEST["permission"] == "public" || $_REQUEST["permission"] === "private" )
        {
            $prj->isListed = true;
        }
        else
        {
            $prj->isListed = false;
        }
        $prj->isPublic = $_REQUEST["permission"] == "public";
        $prj->publicSubmission = true;

        $p_res = $prj->post($this->db, $this->auth);
        if($p_res !== true)die($p_res);

        $res = $prj->setManagers($this->db, $this->auth, $this->auth->getUserEmail());

        // TODO : add submitter $prj->setProjectPermissions($submitters,1);

        if($res === true)
        {
            
                 EpiCollectWebApp::Redirect($this->base_url . preg_replace("/create.*$/", $prj->name, $url));
        }
        else
        {
                $vals = array("error" => $res);
                return applyTemplate("base.html","error.html",$vals);
        }
    }

    function projectHome()
    {
        $url = $this->get_request_url();
        $eou = strlen($url) - 1;
        if($url{$eou} == '/')
        {
                $url{$eou} = '';
        }
        $url = ltrim($url, '/');

        $prj = new EcProject();
        if(array_key_exists('name', $_GET))
        {
            $prj->name = $_GET['name'];
        }
        else
        {
            $prj->name = preg_replace('/\.(xml|json)$/', '', $url);
        }

        $prj->fetch($this->db);

        if(!$prj->id)
        {
            $vals = array('error' => 'Project could not be found');
            return $this->applyTemplate('base.html','./404.html', $vals);
        }


        $loggedIn = $this->auth->isLoggedIn();
        $role = $prj->checkPermission($this->db, $this->auth, $this->auth->getEcUserId());

        if( !$prj->isPublic && !$loggedIn && !preg_match('/\.xml$/',$url) )
        {
            EpiCollectWebApp::flash('This is a private project, please log in to view the project.');
            $this->loginHandler($url);
            return;
        }
        else if( !$prj->isPublic && $role < 2 && !preg_match('/\.xml$/',$url) )
        {
            EpiCollectWebApp::flash(sprintf('You do not have permission to view %s.', $prj->name));
            EpiCollectWebApp::Redirect(sprintf('http://%s/%s', $_SERVER['HTTP_HOST'], $SITE_ROOT));
            return;
        }



        //echo strtoupper($_SERVER["REQUEST_METHOD"]);
        $reqType = strtoupper($_SERVER['REQUEST_METHOD']);
        if( $reqType == 'POST' ) //
        {
            //echo 'POST';
            // update project
            $prj->description = $_POST['description'];
            $prj->image = $_POST['image'];
            $prj->isPublic = array_key_exists('isPublic', $_POST) && $_POST['isPublic'] == 'on' ?  1 : 0;
            $prj->isListed =  array_key_exists('isListed', $_POST) && $_POST['isListed'] == 'on' ?  1 : 0;
            $prj->publicSubmission =  array_key_exists('publicSubmission', $_POST) && $_POST['publicSubmission'] == 'on' ?  1 : 0;

            $res = $prj->id ? $prj->push() : $prj->post();
            if( $res !== true )
            {
                    echo $res;
            }

            if( $_POST['admins'] && $res === true )
            {
                    $res = $prj->setAdmins($_POST["admins"]);
            }

            if( $_POST['users'] && $res === true )
            {
                    $res = $prj->setUsers($_POST["users"]);
            }

            if( $_POST['submitters'] && $res === true )
            {
                    $res = $prj->setSubmitters($_POST['submitters']);
            }
            echo $res;
        }
        elseif( $reqType == 'DELETE' )
        {
            if( $role  == 3 )
            {
                $res = $prj->deleteProject();
                if( $res === true )
                {
                    EpiCollectWebApp::OK();
                    echo '{ "success": true }';
                    return;
                }
                else
                {
                    EpiCollectWebApp::Fail();
                    echo ' {"success" : false, "message" : "Could not delete project" }';
                }
            }
            else
            {
                EpiCollectWebApp::Denied(" delete this project");
                echo ' {"success" : false, "message" : "You do not have permission to delete this project" }';
            }

        }
        elseif( $reqType == 'GET' )
        {
            EpiCollectWebApp::DoNotCache();
            if( array_key_exists('HTTP_ACCEPT', $_SERVER) ) $format = substr($_SERVER["HTTP_ACCEPT"], strpos($_SERVER["HTTP_ACCEPT"], "{$this->site_root}/") + 1 );
            $ext = substr($url, strrpos($url, '.') + 1);
            $format = $ext != '' ? $ext : $format;
            if( $format == 'xml' )
            {
                EpiCollectWebApp::ContentType('xml');
                echo $prj->toXML($this->base_url);
            }else {
                EpiCollectWebApp::ContentType('html');

                try{
                    //$userMenu = '<h2>View Data</h2><span class="menuItem"><img src="images/map.png" alt="Map" /><br />View Map</span><span class="menuItem"><img src="images/form_view.png" alt="List" /><br />List Data</span>';
                    //$adminMenu = '<h2>Project Administration</h2><span class="menuItem"><a href="./' . $prj->name . '/formBuilder.html"><img src="'.$SITE_ROOT.'/images/form_small.png" alt="Form" /><br />Create or Edit Form(s)</a></span><span class="menuItem"><a href="editProject.html?name='.$prj->name.'"><img src="'.$SITE_ROOT.'/images/homepage_update.png" alt="Home" /><br />Update Project</a></span>';
                    $tblList = '';
                    foreach( $prj->tables as $tbl )
                    {
                            $tblList .= "<div class=\"tblDiv\"><a class=\"tblName\" href=\"{$prj->name}/{$tbl->name}\">{$tbl->name}</a><a href=\"{$prj->name}/{$tbl->name}\">View All Data</a> | <form name=\"{$tbl->name}SearchForm\" action=\"./{$prj->name}/{$tbl->name}\" method=\"GET\"> Search for {$tbl->key} <input type=\"text\" name=\"{$tbl->key}\" /> <a href=\"javascript:document.{$tbl->name}SearchForm.submit();\">Search</a></form></div>";
                    }

                    $imgName = $prj->image ? $prj->image : "images/projectPlaceholder.png";

                    if( file_exists($imgName) )
                    {
                            $imgSize = getimagesize($imgName);
                    }
                    else
                    {
                            $imgSize = array(0,0);
                    }

                    $adminMenu = '';
                    $curpage = sprintf('http://%s/%s', $_SERVER['HTTP_HOST'], $this->base_url,  trim($url ,'/'));

                    if( $role == 3 )
                    {
                        $adminMenu = "<span class=\"button-set\"><a href=\"{$curpage}/manage\" class=\"button\">Manage Project</a> <a href=\"{$curpage}/formBuilder\" class=\"button\">Edit Forms</a></span>";
                    }

                    $vals =  array(
                        'projectName' => $prj->name,
                        'projectDescription' => $prj->description && $prj->description != "" ? $prj->description : "Project homepage for {$prj->name}",
                        'projectImage' => $this->base_url . '/' . $imgName,
                        'imageWidth' => $imgSize[0],
                        'imageHeight' =>$imgSize[1],
                        'tables' => $tblList,
                        'adminMenu' => $adminMenu,
                        'userMenu' => ''
                    );


                    return $this->applyTemplate('project_base.html','projectHome.html',$vals);
                    
                }
                catch( Exception $e )
                {

                    $vals = array('error' => $e->getMessage());
                    return $this->applyTemplate('base.html','error.html',$vals);
                }
            }
        }
    }
    
    function getClusterMarker()
    {
        include '/utils/markers.php';
        $colours = trim(EpiCollectUtils::array_get_if_exists($_GET, "colours"), '|');
        $counts = trim(EpiCollectUtils::array_get_if_exists($_GET, "counts"), '|');


        if(!$colours)
        {
            $colours = array("#FF0000");
        }
        else
        {
            $colours = explode("|", $colours);
        }

        if(!$counts)
        {
            $counts = array(111);
        }
        else
        {
            $counts = explode("|", $counts);
        }

        EpiCollectWebApp::ContentType('svg');
        return getGroupMarker($colours, $counts);
    }
    
    function getPointMarker()
    {
	include "./utils/markers.php";
	
	$colour = EpiCollectUtils::array_get_if_exists($_GET, "colour");
	$shape = EpiCollectUtils::array_get_if_exists($_GET, "shape");
	if(!$colour) $colour = "FF0000";
	$colour = trim($colour, "#");
        
	EpiCollectWebApp::ContentType('svg');
	return getMapMaker($colour, $shape);
    }
    
    // Utility and helper functions
    
    function get_request_url()
    {
        $full_path = (array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : $_SERVER["HTTP_X_ORIGINAL_URL"]); //strip off site root and GET query
        $request_url = str_replace($this->site_root, '', $full_path);
        if(strpos($request_url, '?') !== false)
        {
            $only_url = substr($request_url, 0, strpos($request_url, '?'));
        }
        else
        {
            $only_url = $request_url;
        }
    
        $url = urldecode(trim($only_url, '/'));
        return $url;
    }
    
    function makeUrl($fn)
    {
            $root =  trim($this->site_root, '/');
            
            if($root !== '')
            {
                return sprintf('http://%s/%s/ec/uploads/%s', $_SERVER['HTTP_HOST'], $root , $fn);
            }
            else
            {
                return sprintf('http://%s/ec/uploads/%s', $_SERVER['HTTP_HOST'], $fn);
            }
    }
    
    //Static Methods
    
    /**
     * Push a message to be seen by the user when the page reloads or the next page is shown
     * 
     * @param string $msg
     * @param string $type
     * @return void
     */
    static function flash($msg, $type="msg")
    {
	if(!array_key_exists("flashes", $_SESSION) || !is_array($_SESSION["flashes"]))
	{
		$_SESSION["flashes"] = array();
	}
	$nflash = array("msg" => $msg, "type" => $type);

	foreach($_SESSION["flashes"] as $flash)
	{
		if($flash == $nflash )return;
	}
	array_push($_SESSION["flashes"], $nflash);

    }
    
    /**
     * Tell the App not to prevent caching of the page
     */
    static function DoNotCache()
    {
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    /**
     * Tell the app to cache the response for $secs seconds.
     * @param int $secs
     */
    static function CacheFor($secs)
    {
        if(!is_numeric($secs)) throw new Exception ("Expiry must be an integer");
        header(sprintf('Cache-Control: public; max-age=%s;', $secs));
    }
    
    /**
     * sent the user to the specified URL
     * @param string $url
     */
    static function Redirect($url)
    {
        header(sprintf('location: %s', $url));
    }
    
    /**
     * Send a 404 HTTP Response
     * @param type $location
     */
    static function NotFound($location)
    {
        header("HTTP/1.1 404 NOT FOUND", true, 404);
        $vals = array('error' => sprintf('%s could not be found', $location));
        echo applyTemplate('base.html','./404.html', $vals);
    }
    
    
    /**
     * Send an access denied error and send the user ro 
     *
     * @param type $location the location the user was trying to get to
     * @param type $redirect the location to send the user to
     */
    static function Denied($location, $redirect = "/")
    {
        header("HTTP/1.1 403 Access Denied", true, 403);
        EpiCollectWebApp::flash(sprintf('You do not have access to %s', $location));
        EpiCollectWebApp::Redirect($redirect);
    }
    
    /**
     * Send OK Headers
     */
    static function OK()
    {
        header('HTTP/1.1 200 OK', true, 200);
    }
    
     /**
     * Send Internal Error Headers
     */
    static function Fail()
    {
        header('HTTP/1.1 500 OK', true, 500);
    }
    
    /**
     * Send Bad request headers
     */
    static function BadRequest($msg = 'Bad Request')
    {
        header(sprintf('HTTP/1.1 405 %s', $msg));
    }
    
    /**
     * Send the content type header
     */
    static function ContentType($type)
    {
        header(sprintf('Content-type: %s;', EpiCollectWebApp::mimeType($type)));
    }
    
     /**
     * get mime type from an extension or filename
     */
    private static function mimeType($f)
    {
            $mimeTypes = array(
                'ico' => 'image/x-icon',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'jpg' => 'image/jpeg',
                'css' => 'text/css',
                'html' => 'text/html',
                'js' => 'text/javascript',
                'json' => 'text/javascript',
                'xml' => 'text/xml',
                'php' => 'text/html',
                'mp4' => 'video/mp4',
                'csv' => 'text/csv',
                'svg' => 'image/svg+xml',
                'zip' => 'application/zip',
                'kml' => 'application/vnd.google-earth.kml+xml'
            );

            if(stristr($f, '.') !== false)
            {
                $f = preg_replace('/\?.*$/', '', $f);
                $ext = substr($f, strrpos($f, '.') +1);
            }
            else
            {
                $ext = $f;
            }
            if(array_key_exists($ext, $mimeTypes))
            {
                    return $mimeTypes[$ext];
            }
            else
            {
                    return 'text/html';
            }
    }
}
?>
