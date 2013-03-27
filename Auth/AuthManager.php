<?php
 require "./Auth/OpenIDProvider.php";
 require "./Auth/Ldap.php";
 require "./Auth/LocalAuthProvider.php";
 
 class AuthManager
 {
	private $provider;

	private $user;
	
	public $firstName;
	public $lastName;
	public $email;
	public $language = "en";
	
        private $app;
        private $db;
        private $serverManager = false;
	
	private $openIdEnabled = true;
	private $ldapEnabled = true;
	private $localEnabled = true;
	
	function __construct($app, $cfg)
	{	
                $this->app = $app;
                $this->db = $this->app->getDB();
                $this->cfg = $cfg;

		if(array_key_exists("use_openID", $cfg->settings["security"]))
		{
			$this->openIdEnabled = $cfg->settings["security"]["use_openID"] == "true";
		}

		if(array_key_exists("use_ldap", $cfg->settings["security"]))
		{
			$this->ldapEnabled = $cfg->settings["security"]["use_ldap"] == "true";
		}
		
		if(array_key_exists("use_local", $cfg->settings["security"]))
		{
			$this->localEnabled = $cfg->settings["security"]["use_local"] == "true";
		}
		
		$this->providers = array();
		
		if($this->openIdEnabled)
	  	{
	  		try
	  		{
	   			$this->providers["OPENID"] = new OpenIDProvider($this->app);
	  		}
	  		catch(Exception $err)
	  		{
	  			$err = null;
	  			$this->openIdEnabled = false;
	  			
	  		}
	  	}
	   	if($this->ldapEnabled)
	   	{
	   		$this->providers["LDAP"] = new LdapProvider();
	   	}
	   	if($this->localEnabled)
	   	{
	   		$this->providers["LOCAL"] = new LocalLoginProvider();
	   	}
  	}
  	
  	function getProviderType()
  	{
  		//return $this->provider->getType();
  		return $_SESSION['provider'];
  	}
  	
  	function getUserName()
  	{
  		
  		return $this->providers[$_SESSION['provider']]->getUserName($this->getEcUserId());
  	}
        
        public function requestSignup(){
            return $this->providers['LOCAL']->requestSignup();
        }
  
  	function requestlogin($requestedUrl, $provider = "")
  	{  		
            $provider = strtoupper($provider);

            if($requestedUrl === 'login')
            {
                $_SESSION["url"] = $this->app->site_root . '/';
            }
            else
            {
                $_SESSION["url"] = $this->app->site_root . '/' . trim($requestedUrl, '/');
            }    


            if(($provider != "" && $provider != "LOCAL" && array_key_exists($provider, $this->providers)) || count($this->providers) == 1)
            {
                if($provider == '' && count($this->providers) == 1)
                {
                        $keys = array_keys($this->providers);
                        $provider = $keys[0];
                        $_SESSION['provider'] = $provider;
                }
                return $this->providers[$provider]->requestLogin($this->app->base_url . trim($requestedUrl, '/'), false);
            }
            else
            {
                $frm =  "<p>Please Choose a Method to login</p>";
                if($this->localEnabled)$frm .= "<div class=\"login-provider epicollect\"><h3>EpiCollect Account</h3>" .  $this->providers["LOCAL"]->requestLogin("{$this->app->base_url}/" . trim($requestedUrl, '/'), false) . "</div>";
                if($this->openIdEnabled) $frm .= "<div class=\"login-provider google\"><h3>Google/Gmail</h3><a class=\"btn\" href=\"{$this->app->base_url}/$requestedUrl?provider=OPENID\">Google/Gmail account (OpenID)</a></div>";
                if($this->ldapEnabled && array_key_exists("ldap_domain", $cfg->settings["security"]) && $cfg->settings["security"]["ldap_domain"] != "")
                {
                    $frm .= "<a class=\"login-provider\" href=\"{$this->app->base_url}/$requestedUrl?provider=LDAP\">Windows Account ({$cfg->settings["security"]["ldap_domain"]})</a>";
                }
                return $frm;
            }
   		
  	}
  	
  	function setEnabled($uid, $enabled)
  	{
            global $db;

            $enabled = $enabled ? "1" : "0";
            if($uid != $this->getEcUserId())
            {
                    $qry = "UPDATE user SET active = $enabled where idUsers = $uid";
                    return $db->do_query($qry);
            }
            return false;
  	}
  	
  	function resetPassword($uid)
  	{
  		if($this->localEnabled)
  		{
  			return $this->providers["LOCAL"]->resetPassword($uid);
  		}
  		else
  		{
  			 
  			return false;
  		}
  	}
  	
  	function setPassword($uid, $password)
  	{
  		if($this->localEnabled)
  		{
  			return $this->providers["LOCAL"]->setPassword($uid, $password);
  		}
  		else
  		{
  	
  			return false;
  		}
  	}
  
  	function callback($provider = "")
  	{
                
  		if( $this->isLoggedIn() ) 
  		{
  			header("location: {$this->app->base_url}/{$_SESSION["url"]}"); return;
  		}
  		
  		if(!array_key_exists($provider, $this->providers)) {
  			header("location: {$this->app->base_url}/");

  		}
  		  		
  		$res = $this->providers[$provider]->callback();

  		if($res === true)
  		{
  			
  			$uid = false;
  			$sql = "SELECT idUsers, active FROM user where email = '" . $this->providers[$provider]->getEmail()  . "'";
  			
  			//print $this->providers[$provider]->getCredentialString();
  			
  			if($provider != "LOCAL")
  			{
	  			$this->firstName = $this->providers[$provider]->firstName;
	  			$this->lastName = $this->providers[$provider]->lastName;
	  			$this->email = $this->providers[$provider]->email;
	  			$this->language = $this->providers[$provider]->language;
  			}
  			
  			$res_get_user = $this->db->do_query($sql);
  			if($res_get_user !== true) die($res . "\n" . $sql);
  			while($arr = $this->db->get_row_array())
  			{
  		
  				if($arr["active"])
  				{ 
  					$uid = $arr["idUsers"];
  				}
  				else 
  				{
  					flash ("Account is disabled", "err");  	
  					header("location: {$this->app->base_url}/{$_SESSION["url"]}");
  					return;
  				}
  			}
  			if($provider != "LOCAL" && !$uid)
  			{
  				$sql = "INSERT INTO user (FirstName, LastName, Email, details, language, serverManager) VALUES ('{$this->firstName}','{$this->lastName}','{$this->email}','" . $this->providers[$provider]->getCredentialString() . "','{$this->language}', " . (count($this->getServerManagers()) == 0 ?  "1" : "0") . ")";
  				$res = $this->db->do_query($sql);
  				if($res !== true) die($res);
  				$uid = $this->db->last_id();
  				if(!$uid) die("user creation failed $sql");
  			}
  			
  			$dat = new DateTime();
  			$dat = $dat->add(new DateInterval("PT{$this->cfg->settings["security"]["session_length"]}S"));
  			$sql = "INSERT INTO ecsession (id, user, expires) VALUES ('" . session_id() . "', $uid, " . $dat->getTimestamp() . ");";
  			
  			$res = $this->db->do_query($sql);
  			if($res !== true && !preg_match("/Duplicate Key/i", $res)) die($res . "\n" . $sql);
  
  			header("location: {$_SESSION["url"]}");
  			return;
  		}
  		else
  		{
  			EpiCollectWebApp::flash("Login failed, please try again");
  			if(!array_key_exists("tries", $_SESSION))
  			{
  				$_SESSION["tries"] = 1;
  			}  			
  			else
  			{
  				if($_SESSION["tries"] < 6) $_SESSION["tries"]++;
  			}
  			//sleep($_SESSION["tries"] * $_SESSION["tries"]);
  			
  			header("location: {$this->app->base_url}/login.php");
  		}
  	}
  	
  	function logout($provider = "")
  	{
  		//if(!array_key_exists($provider, $this->providers)) return false;
  		  		  		
  		$res = $this->db->do_query("DELETE FROM ecsession WHERE id = '" . session_id() . "'");
  		if(!$res) die("$res - $sql");
  		$_SESSION['provider'] = null;
  		$params = session_get_cookie_params();
	    setcookie(session_name(), '', time() - 42000,
	        $params["path"], $params["domain"],
	        $params["secure"], $params["httponly"]
	    );
  		//$this->providers[$provider]->logout();

  	}
  
  	function isLoggedIn()
  	{
  		if(!$this->db || !$this->db->connected) return false;
                
  		$dat = new DateTime();
  		$qry = "DELETE FROM ecsession WHERE expires < ". $dat->getTimestamp();
  		 		
  		$res = $this->db->do_query($qry);
  		if($res !== true) return false;
  		
  		$this->user = false;
  		
  		$qry = "select user, firstName, lastName, email, serverManager from ecsession left join user on ecsession.user = user.idUsers WHERE ecsession.id = '" . session_id() ."'"; 
  		$res = $this->db->do_query($qry);
  		if($res !== true) die($res . "\n" . $qry);
  		
  		while ($arr = $this->db->get_row_array()){ 
  			$this->user = $arr["user"]; 
  			$this->firstName = $arr["firstName"];
  			$this->lastName = $arr["lastName"];
  			$this->email = $arr["email"];
  			$this->serverManager = $arr["serverManager"];
  		}
		$this->db->free_result();
   		return $this->user !== false;
  	}
  
  	function isServerManager()
  	{
  		return !!$this->serverManager;
  	}
  	
  	function makeServerManager($email)
  	{
  		$qry = "select serverManager from user WHERE email = '$email'";
  		$res = $db->do_query($qry);
  		$r=0;$u=0;
  		while ($arr = $this->db->get_row_array())
  		{
  			$u++;
  			$r += $arr["serverManager"];
  		}
  		
  		if($u == 0) return 0;
  		if($r > 0) return -1;
  		
  		$qry = "UPDATE user SET serverManager = 1 WHERE email = '$email'";
  		if($db->do_query($qry) !== true) die("oops"); 
  		return 1;
  	}
  	
  	function removeServerManager($email)
  	{
  		$qry = "UPDATE user SET serverManager = 0 WHERE email = '$email'";
  		if($this->db->do_query($qry) !== true) die("oops");
  	}
  	
  	function getServerManagers()
  	{
  		try{
  		
  			$men = array();
  			if($this->db)
  			{
		  		$qry = "SELECT firstName, lastName, Email FROM user WHERE serverManager = 1 and active = 1";
		  		$res = $this->db->do_query($qry);
		  		if($res !== true) throw new Exception(sprintf('MySQL error :  %s last successful query was %s',$res, $db->lastQuery));
				
		  		while($arr = $this->db->get_row_array())
		  		{
		  			array_push($men, $arr);
		  		}
		  		$this->db->free_result();
  			}
  			//
	  		return $men;
  		}
	  	catch(ErrorException $err)
	  	{
	  		return array();
	  	}
  	}
  	
  	private function populateSesssionInfo()
  	{
	   $qry = "SELECT idUsers as userId, FirstName, LastName, Email, language FROM user WHERE openId = '{$_SESSION['openid']}'";
	   $err = $this->db->do_query($qry);
	   if($err === true)
	   {
			if($arr = $this->db->get_row_array())
			{
	 			foreach(array_keys($arr) as $key)
	 			{
	  				$_SESSION[$key] = $arr[$key];
	 			}
			}
   		}
	  }
	  
	  function createUser($username, $pass, $email, $firstName, $lastName, $language)
	  {
	  	global $hasManagers;
	  	if($this->localEnabled)
	  	{
	  	 	$res =  $this->providers["LOCAL"]->createUser($username, $pass, $email, $firstName, $lastName, $language, !$hasManagers);
	  
	  	 	if($res === true)
	  	 	{
	  	 		if(!$hasManagers) EpiCollectWebApp::flash('Please sign in with the user account you have just created.');
	  	 		return true;
	  	 	}
	  	 	elseif(preg_match("/Duplicate entry '.*' for key 'Email'/", $res))
	  	 	{
	  	 		EpiCollectWebApp::flash("A user already exists with that email address", "err");
	  	 	}
	  	}
	  	else
	  	{
	  		return false;
	  	}
	  }
	  
	  function getUsers($order = "FirstName", $dir = "asc")
	  {
                    $query = "SELECT idUsers as userId, FirstName, LastName, Email, active FROM user ORDER BY $order $dir";
                    $res = $this->db->do_query($query);
                    if(!$res === true)
                    {
                            $log->write("err", $res);
                            return false;
                    }
                    else
                    {
                            $ret = array();
                            while($arr = $this->    db->get_row_array())
                            {
                                    array_push($ret, $arr);
                            }
                            return $ret;
                    }
	  }
	  
	  function getUserNickname()
	  {
		   //$arr = $this->openid->getAttributes();
		   
		   return "{$this->firstName} {$this->lastName}";
	  }
	  
	  function getEcUserId()
	  {
	   	return $this->user;
	  }
	  
	  function getUserEmail()
	  {
		  	//$arr = $this->openid->getAttributes();
			//   echo $arr["contact/email"];
		   return $this->email;
	  }
}
?>
