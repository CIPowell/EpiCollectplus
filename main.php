<?php
include ('./Classes/PageSettings.php');
include ('./Classes/configManager.php');
include ('./Classes/Logger.php');
/*
 * Ec Class declatratioions
 */
include('./Classes/EcProject.php');
include('./Classes/EcTable.php');
include('./Classes/EcField.php');
include ('./Classes/EcOption.php');
include ('./Classes/EcEntry.php');
include ('./utils/HttpUtils.php');
include ('./Auth/AuthManager.php');
include ('./db/dbConnection.php');
include ('./utils/EpiCollectUtils.php');
include ('./WebApp.php');
//$dat = new DateTime('now');
//$dfmat = '%s.u';
function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
{
	// error was suppressed with the @-operator
	if (0 === error_reporting()) {
		return false;
	}
	
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}


//set_error_handler('handleError', E_ALL);

$app = new EpiCollectWebApp();
$app->before_first_request();
$app->before_request();
$app->process_request();



/* class and function definitions */

function setupDB()
{
	global $cfg, $auth, $SITE_ROOT;

	try{
		$db = new EpiCollectDatabaseConnection($_POST["un"], $_POST["pwd"]);
			
	}catch(Exception $e)
	{
		$_GET["redir"] = "pwd";
		siteTest();
		return ;
	}
	if(!$db)
	{
		echo "DB not connected";
		return;
	}

	$sql = file_get_contents("./db/epicollect2.sql");

	$qrys = explode("~", $sql);

	for($i = 0 ; $i < count($qrys); $i++)
	{
		if($qrys[$i] != "")
		{

			$res = $db->do_multi_query($qrys[$i]);
			if($res !== true && !preg_match("/already exists|Duplicate entry .* for key/", $res))
			{
				siteHome();
				return;
			}
		}
	}
	
	EpiCollectWebApp::flash('Please sign in to register as the first administartor of this server.');
	EpiCollectWebApp::Redirect(sprintf('http://%s%s/login.php' , $_SERVER['HTTP_HOST'], $SITE_ROOT));
	return;
}

function formDataLastUpdated()
{
    global $url,  $log, $auth;

	$http_accept = EpiCollectUtils::array_get_if_exists($_SERVER, 'HTTP_ACCEPT');
	$format = ($http_accept ? substr($http_accept, strpos($http_accept, '/') + 1) : '');
	$ext = substr($url, strrpos($url, ".") + 1);
	$format = $ext != "" ? $ext : $format;

	$prj = new EcProject();
	$pNameEnd = strpos($url, "/");

	$prj->name = substr($url, 0, $pNameEnd);
	$prj->fetch();
	
	if(!$prj->id)
	{
		echo applyTemplate("./base.html", "./error.html", array("errorType" => "404 ", "error" => "The project {$prj->name} does not exist on this server"));
		return;
	}
	
	$permissionLevel = 0;
	$loggedIn = $auth->isLoggedIn();
	
	if($loggedIn) $permissionLevel = $prj->checkPermission($auth->getEcUserId());

	if(!$prj->isPublic && !$loggedIn)
	{
		loginHandler($url);
		return;
	}
	else if(!$prj->isPublic &&  $permissionLevel < 2)
	{
		echo applyTemplate("./base.html", "./error.html", array("errorType" => "403 ", "error" => "You do not have permission to view this project"));
		return;
	}

	$extStart = strpos($url, ".");
	$frmName = substr($url, $pNameEnd + 1, strrpos($url, '/', 1) - strlen($url));

        
        
	if(!array_key_exists($frmName, $prj->tables))
	{
		echo applyTemplate("./base.html", "./error.html", array("errorType" => "404 ", "error" => "The project {$prj->name} does not contain the form $frmName"));
		return;
	}
        
        echo json_encode($prj->tables[$frmName]->getLastActivity());
        return;
}



/* end of class and function definitions */

/* handlers	*/

function defaultHandler()
{
	global $url;
	EpiCollectWebApp::ContentType(mimeType($url));
	echo applyTemplate('base.html', "./" . $url);
}

function createAccount()
{
    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
        global $cfg;
        if($cfg->settings['misc']['public_server'] === "true")
        {
            createUser();
            EpiCollectWebApp::flash("Account created, please log in.");
            EpiCollectWebApp::Redirect(sprintf('http://%s/%s/login.php', $server, $root));
        }
        else
        {
            EpiCollectWebApp::flash("This server is not public", "err");
            EpiCollectWebApp::Redirect(sprintf('http://%s/%s/', $server, $root));
        }   
    } else {
        global $auth;
        echo applyTemplate('./base.html', './loginbase.html', array( 'form' => $auth->requestSignup()));
    }
    
}



function uploadHandlerFromExt()
{
	global $log;
	//$flog = fopen('fileUploadLog.log', 'w');
	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(count($_FILES) > 0)
		{
			$keys = array_keys($_FILES);
			foreach($keys as $key)
			{
				if($_FILES[$key]['error'] > 0)
				{
					//fwrite($flog, $key . " error : " .$_FILES[$key]['error']);
					$log->write("error", $key . " error : " .$_FILES[$key]['error'] );
				}
				else
				{
					if(preg_match( "/.(png|gif|rtf|docx?|pdf|jpg|jpeg|txt|avi|mpeg|mpg|mov|mp3|wav)$/i", $_FILES[$key]['name']))
					{
                                                if(!file_exists("ec/uploads/")) mkdir ("ec/uploads/");
						move_uploaded_file($_FILES[$key]['tmp_name'], "ec/uploads/{$_FILES[$key]['name']}");
						echo  "{\"success\" : true , \"msg\":\"ec/uploads/{$_FILES[$key]['name']}\"}";
					}
					else
					{
						echo  " error : file type not allowed";
							
					}
				}
			}
		}
		else
		{
			echo "No file submitted";
		}
	}
	else
	{
		echo "Incorrect method";
	}
	fclose($flog);
}



function siteTest()
{
    $res = array();
    global $cfg, $db;

    $template = 'testResults.html';

    $doit = true;
    if(!array_key_exists("database", $cfg->settings) || !array_key_exists("server", $cfg->settings["database"]) ||trim($cfg->settings["database"]["server"]) == "")
    {
        $res["dbStatus"] = "fail";
        $res["dbResult"] = "No database server specified, please amend the file ec/settings.php and so that \$DBSERVER equals the name of the MySQL server";
        $doit = false;
    }
    else if(!array_key_exists("user", $cfg->settings["database"]) || trim($cfg->settings["database"]["user"]) == "")
    {
        $res["dbStatus"] = "fail";
        $res["dbResult"] = "No database user specified, please amend the file ec/settings.php so that \$DBUSER and \$DBPASS equal the credentials for MySQL server";
        $doit = false;
    }
    else if(!array_key_exists("database", $cfg->settings["database"]) ||trim($cfg->settings["database"]["database"]) == "")
    {
        $res["dbStatus"] = "fail";
        $res["dbResult"] = "No database name specified, please amend the file ec/settings.php so that \$DBNAME equals the name of the MySQL database";
        $doit = false;
    }

    if($doit && !(array_key_exists("edit", $_GET) && $_GET["edit"] === "true"))
    {
        if(array_key_exists("redir", $_GET) && $_GET["redir"] === "true") $res["redirMsg"] = "	<p class=\"message\">You have been brought to this page because of a fatal error opening the home page</p>";
        if(array_key_exists("redir", $_GET) && $_GET["redir"] === "pwd") $res["redirMsg"] = "	<p class=\"message\">The username and password you entered were incorrect, please try again.</p>";

        if(!$db) $db = new EpiCollectDatabaseConnection();


        if($db->connected)
        {
            $res["dbStatus"] = "succeed";
            $res["dbResult"] = "Connected";
        }
        else
        {
            $ex = $db->errorCode;
            if($ex == 1045)
            {
                $res["dbStatus"] = "fail";
                $res["dbResult"] = "DB Server found, but the combination of the username and password invalid. <a href=\"./test?edit=true\">Edit Settings</a>";
            }
            elseif($ex == 1044)
            {
                $res["dbStatus"] = "fail";
                $res["dbResult"] = "DB Server found, but the database specified does not exist or the user specified does not have access to the database. <a href=\"./test?edit=true\">Edit Settings</a>";
            }
            else
            {
                $res["dbStatus"] = "fail";
                $res["dbResult"] =  "Could not find the DB Server ";
            }
        }

        if($db->connected)
        {
                $dbNameRes = $db->do_query("SHOW DATABASES");
                if($dbNameRes !== true)
                {
                    echo $dbNameRes;
                    return;
                }
                while($arr = $db->get_row_array())
                {

                    if( $arr['Database'] == $cfg->settings["database"]["database"])
                    {
                        $res["dbStatus"] = "succeed";
                        $res["dbResult"] = "";
                        break;
                    }
                    else
                    {
                        $res["dbStatus"] = "fail";
                        $res["dbResult"] = "DB Server found, but the database '{$cfg->settings["database"]["database"]}' does not exist.<br />";
                    }
                }

                $res["dbPermStatus"] = "fail";
                $res["dbPermResults"] = "";
                $res["dbTableStatus"] = "fail";

                if($res["dbStatus"] == "succeed")
                {
                    $dbres = $db->do_query("SHOW GRANTS FOR {$cfg->settings["database"]["user"]};");
                    if($dbres !== true)
                    {
                        $res["dbPermResults"] = $res;
                    }
                    else
                    {
                        $perms = array("SELECT", "INSERT", "UPDATE", "DELETE", "EXECUTE");
                        $res ["dbPermResults"] = "Permssions not set, the user {$cfg->settings["database"]["user"]} requires SELECT, UPDATE, INSERT, DELETE and EXECUTE permissions on the database {$cfg->settings["database"]["database"]}";
                    while($arr = $db->get_row_array())
                    {
                        $_g = implode(" -- ", $arr) . "<br />";
                        if(preg_match("/ON (`?{$cfg->settings["database"]["database"]}`?|\*\.\*)/", $_g))
                        {
                            if(preg_match("/ALL PERMISSIONS/i", $_g))
                            {
                                $res["dbPermStatus"] = "fail";
                                $res["dbPermResults"] = "The user account {$cfg->settings["database"]["user"]} by the website should only have SELECT, INSERT, UPDATE, DELETE and EXECUTE priviliges on {$cfg->settings["database"]["database"]}";
                                break;
                            }
                            for($_p = 0; $_p < count($perms); $_p++)
                            {
                                if(preg_match("/{$perms[$_p]}/i", $_g)) // &&  preg_match("/INSERT/", $_g) &&  preg_match("/UPDATE/", $_g) &&  preg_match("/DELETE/", $_g) &&  preg_match("/EXECUTE/", $_g))
                                {
                                    unset($perms[$_p]);
                                    $perms = array_values($perms);
                                    $_p--;
                                }
                            }
                        }
                    }
                    if(count($perms) == 0)
                    {
                        $res["dbPermStatus"] = "succeed";
                        $res["dbPermResults"] = "Permssions Correct";
                    }
                    else
                    {
                        $res ["dbPermResults"] = "Permssions not set, the user {$cfg->settings["database"]["user"]} is missing " . implode(", ", $perms) .  " permissions on the database {$cfg->settings["database"]["database"]}";
                    }
                }
            }
        }

        if($db->connected && $res["dbPermStatus"] == "succeed")
        {
            $tblTemplate = array(
                "device" => false,
                "deviceuser" => false,
                "enterprise" => false,
                "entry" => false,
                "entryvalue" => false,
                "entryvaluehistory" => false,
                "field" => false,
                "fieldtype" => false,
                "form" => false,
                "option" => false,
                "project" => false,
                "role" => false,
                "user" => false,
                "userprojectpermission" => false	
            );

            $dres = $db->do_query("SHOW TABLES");
            if($dres !== true)
            {
                $res["dbTableStatus"] = "fail";
                $res["dbTableResult"] = "EpiCollect Database is not set up correctly";
            }
            else
            {
                $i = 0;
                while($arr = $db->get_row_array())
                {
                    $tblTemplate[$arr["Tables_in_{$cfg->settings["database"]["database"]}"]] = true;
                    $i++;
                }
                if($i == 0)
                {
                    $template = 'dbSetup.html';
                    $res["dbTableStatus"] = "fail";
                    $res["dbTableResult"] = "<p>Database is blank,  enter an <b>administrator</b> username and password for the database to create the database tables.</p>
                <form method=\"post\" action=\"createDB\">
                        <b>Username : </b><input name=\"un\" type=\"text\" /> <b>Password : </b><input name=\"pwd\" type=\"password\" /> <input type=\"hidden\" name=\"create\" value=\"true\" /><input type=\"submit\" value=\"Create Database\" name=\"Submit\" />
                </form>";
                }
                else
                {
                    $done = true;
                    foreach($tblTemplate as $key => $val)
                    {
                        $done &= $val;
                    }

                    if($done)
                    {
                        $res["dbTableStatus"] = "succeed";
                        $res["dbTableResult"] = "EpiCollect Database ready";
                    }
                    else
                    {
                        $res["dbTableStatus"] = "fail";
                        $res["dbTableResult"] = "EpiCollect Database is not set up correctly";
                    }
                }
            }

        }

        $res["endStatus"] = array_key_exists("dbTableStatus", $res) ? ($res["dbTableStatus"] == "fail" ? "fail" : "") : "fail";
        $res["endMsg"] = ($res["endStatus"] == "fail" ? "The MySQL database is not ready, please correct the errors in red above and refresh this page. <a href = \"./test?edit=true\">Configuration tool</a>" : "You are now ready to create EpiCollect projects, place xml project definitions in {$_SERVER["PHP_SELF"]}/xml and visit the <a href=\"createProject.html\">create project</a> page");
        echo applyTemplate("base.html", $template, $res);
    }
    else
    {
        $arr = "{";
        foreach($cfg->settings as $k => $v)
        {
            foreach($v as $sk => $sv)
            {
                $arr .= "\"{$k}\\\\{$sk}\" : \"$sv\",";
            }
        }
        $arr = trim($arr, ",") . "}";

        echo applyTemplate("base.html", "setup.html", array("vals" => $arr));
    }	
}



function uploadData()
{
	global  $url, $log;
	$flog = fopen('ec/uploads/fileUploadLog.log', 'a');
	$prj = new EcProject();
	$prj->name = preg_replace('/\/upload\.?(xml|json)?$/', '', $url);

	$prj->fetch();
	
	if($_SERVER["REQUEST_METHOD"] == "POST"){
		if(count($_POST) == 0)
		{
			parse_str(file_get_contents("php://input"), $_POST);
		}
		
		if(count($_FILES) > 0)
		{
			foreach($_FILES as $file){
					
				if(preg_match("/.+\.xml$/", $file["name"])){
					$ts = EpiCollectUtils::getTimestamp();
						

					$fn = "$ts-{$file["name"]}";

					for($i = 1; file_exists("../ec/rescue/{$fn}"); $i++)

					{
						$fn = "$ts-$i-{$file['name']}";
					}
					move_uploaded_file($file['tmp_name'], "./ec/rescue/{$fn}");

					$res = $prj->parseEntries(file_get_contents("./ec/rescue/{$fn}"));

					if(preg_match("/(CHROME|FIREFOX)/i", $_SERVER["HTTP_USER_AGENT"]))
					{
						echo $res;
					}
					else
					{

						//fwrite($flog, "$res\r\m");
						$log->write("debug", "$res");
						echo ($res === true ? "1" : "0");
					}
				}
				else if(preg_match("/\.(png|gif|rtf|docx?|pdf|jpg|jpeg|txt|avi|mpe?g|mov|mpe?g?3|wav|mpe?g?4)$/", $file['name']))
				{

					try{
						//if(!fileExists("./uploads/{$prj->name}")) mkdir("./uploads/{$prj->name}");
							
						move_uploaded_file($file['tmp_name'], "./ec/uploads/{$prj->name}~" . ($_REQUEST["type"] == "thumbnail" ? "tn~" : "" ) ."{$file['name']}");
						$log->write('debug', $file['name'] . " copied to uploads directory\n");
						echo 1;
					}
					catch(Exception $e)
					{
						$log->write("error", $e . "\r\n");
						echo "0";
					}
				}
				else
				{
					$log->write("error", $file['name'] . " error : file type not allowed\r\n");
					echo "0";
				}
			}

		}
		else
		{
			$log->write("POST", "data : " . serialize($_POST) . "\r\n");
			$tn = $_POST["table"];
			unset($_POST["table"]);

			try
			{
				 
				$ent = new EcEntry($prj->tables[$tn]);
				if(array_key_exists("ecPhoneID", $_POST))
				{
					$ent->deviceId = $_POST["ecPhoneID"];
				}
				else
				{
					$ent->deviceId = "web";
				}
				if(array_key_exists("ecTimeCreated", $_POST))
				{
					$ent->created = $_POST["ecTimeCreated"];
				}
				else
				{
					$ent->created = EpiCollectUtils::getTimestamp();
				}
				$ent->project = $prj;
					
				foreach($prj->tables[$tn]->fields as $key => $fld){
					if($fld->type == 'gps' || $fld->type == 'location')
					{
						$lat = "{$key}_lat";
						$lon = "{$key}_lon";
						$alt = "{$key}_alt";
						$acc = "{$key}_acc";
						$src = "{$key}_provider";
						$bearing = "{$key}_bearing";
						
						$ent->values[$key] = array(
							'latitude' => (string) EpiCollectUtils::array_get_if_exists($_POST, $lat),
							'longitude' => (string)EpiCollectUtils::array_get_if_exists($_POST,$lon),
							'altitude' => (string)EpiCollectUtils::array_get_if_exists($_POST,$alt),
							'accuracy' => (string) EpiCollectUtils::array_get_if_exists($_POST,$acc), 
							'provider' => (string)EpiCollectUtils::array_get_if_exists($_POST,$src),
							'bearing' =>  (string)EpiCollectUtils::array_get_if_exists($_POST,$bearing),
						);
					}
					else if(!array_key_exists($key, $_POST))
					{
						$ent->values[$key] = "";
						continue;
					}
					else if($fld->type != "branch")
					{
						$ent->values[$key] = (string)$_POST[$key];
					}
				}
				
				$log->write("debug", "posting ... \r\n");
				$res = $ent->post();
				$log->write("debug",  "response : $res \r\n");
					
				if($res === true)
				{
                                    EpiCollectWebApp::OK();
					echo 1;
				}
				else
				{
                                    EpiCollectWebApp::BadRequest();
					$log->write("error",  "error : $res\r\n");
					echo $res;
				}
			}
			catch(Exception $e)
			{
				$log->write("error",  "error : " . $e->getMessage() . "\r\n");
				$msg = $e->getMessage();
				if(preg_match("/^Message/", $msg))
				{
                                    EpiCollectWebApp::BadRequest($msg);
				}
				else
				{
                                    EpiCollectWebApp::BadRequest();
				}
				echo $msg;
			}
		}
	}
	fclose($flog);
}

function getChildEntries($survey, $tbl, $entry, &$res, $stopTbl = false)
{
	//	global $survey;

	foreach($survey->tables as $subTbl)
	{
			
		if(($subTbl->number <= $tbl->number && $subTbl->branchOf != $tbl->name)||($stopTbl !== false && $subTbl->number > $stopTbl && $subTbl->branchOf != $tbl->name)){
			continue;
		}
			
		foreach($subTbl->fields as $fld)
		{
			if($fld->name == $tbl->key && !array_key_exists($subTbl->name, $res))
			{
					
				$res[$subTbl->name] = $subTbl->get(Array($tbl->key => $entry));
				//print_r($res[$subTbl->name]);
				foreach($res[$subTbl->name][$subTbl->name] as $sEntry)
				{

					getChildEntries($survey, $subTbl, $sEntry[$subTbl->key][$subTbl->key], $res, $stopTbl);

				}
					
			}

		}
	}

}

function downloadData()
{
	global  $url, $SITE_ROOT;
	EpiCollectWebApp::DoNotCache();

	//$flog = fopen('ec/uploads/fileUploadLog.log', 'a+');
	$survey = new EcProject();
	$survey->name = preg_replace("/\/download\.?(xml|json)?$/", "", $url);

	$survey->fetch();

	$lastUpdated = $survey->getLastUpdated();
	$qString = $_SERVER["QUERY_STRING"];

	$baseFn = md5($lastUpdated . $qString);
	//the root of the working directory is the Script filename minus everthing after the last \
	//NOTE: This will be the same for EC+ as the upload directory is project-independant
	$pos = max(strrpos($_SERVER["SCRIPT_FILENAME"], "\\") ,strrpos($_SERVER["SCRIPT_FILENAME"], "/"));
	$root =substr($_SERVER["SCRIPT_FILENAME"], 0, $pos);
	
	$wwwroot = "http://{$_SERVER["HTTP_HOST"]}$SITE_ROOT";
	$startTbl = (array_key_exists('select_table', $_GET) ? EpiCollectUtils::array_get_if_exists($_GET, "table") : false);
	$endTbl = (array_key_exists('select_table', $_GET) ? EpiCollectUtils::array_get_if_exists($_GET, "select_table") :  EpiCollectUtils::array_get_if_exists($_GET, "table"));
	$entry = EpiCollectUtils::array_get_if_exists($_GET, "entry");
	$dataType = (array_key_exists('type', $_GET) ? $_GET["type"] : "data");
	$xml = !(array_key_exists('xml', $_GET) && $_GET['xml'] === "false");

	$files_added = 0;

	$delim = "\t";
	$rowDelim = "\n";

	$tbls = array();
	$branches = array();

	$n = $startTbl ? $survey->tables[$startTbl]->number : 1;
	$end = $endTbl ? $survey->tables[$endTbl]->number : count($survey->tables);

	// if we're doing a select_table query we don't want the data from the first table, as we already have that entry.
	if(array_key_exists('select_table', $_GET) && $entry) $n++;

	//for each table between startTbl and end Tbl (or that is a branch of a table we want)
	//we'll loop through the table array to establish which tables we need
	foreach($survey->tables as $name => $tbl)
	{
		//first off is $tbl is already in $tbls we can skip it
		if(array_key_exists($name, $tbls))
		{
			continue;
		}
		
		// are we doing name-based or type-based checking?
		elseif( $dataType  == 'group' )
		{
			if( $tbl->group )
			{
				array_push($tbls, $name);
			}
		}
		else
		{
			// first check if the table has a number between $n and $end
			if( ($tbl->number >= $n && $tbl->number <= $end) )
			{
				array_push($tbls, $name);
			}
			
			if( count($tbl->branches) > 0 )
			{
				$tbls = array_merge($tbls, $tbl->branches);
			}	
		}
	}

	if( $dataType  == 'group' ) $dataType = 'data';
	
	//criteria
	$cField = false;
	$cVals = array();
	if( $entry )
	{
		$cField = $survey->tables[$startTbl]->key;
		$cVals[0] = $entry;
	}

	$nxtCVals = array();
		
	//for each main table we're intersted in (i.e. main tables between stat and end table)
	//$ts = new DateTime("now", new DateTimeZone("UTC"));
	//$ts = $ts->EpiCollectUtils::getTimestamp();
	if( $dataType == 'data' && $xml )
	{
            EpiCollectWebApp::ContentType('xml');
		$fxn = "$root\\ec\\uploads\\{$baseFn}.xml";
		$fx_url = "$wwwroot/ec/uploads/{$baseFn}.xml";
		if(file_exists($fxn))
		{
			EpiCollectWebApp::Redirect($fx_url);
			return;
		}
		$fxml = fopen("$fxn", "w+");
		fwrite($fxml,"<?xml version=\"1.0\"?><entries>");
			
	}
	else if($dataType == "data")
	{
            EpiCollectWebApp::ContentType('plain');
		$txn = "$root\\ec\\uploads\\{$baseFn}.tsv";
		$ts_url = "$wwwroot/ec/uploads/{$baseFn}.tsv";
		if(file_exists($txn))
		{
                    EpiCollectWebApp::Redirect($ts_url);
			return;
		}
			
		$tsv = fopen($txn, "w+");
	}
	else
	{
                EpiCollectWebApp::ContentType('zip');
		$zfn = "$root\\ec\\uploads\\arc{$baseFn}.zip";
		$zrl = "$wwwroot/ec/uploads/arc{$baseFn}.zip";
			
		if(file_exists($zfn))
		{
			EpiCollectWebApp::Redirect($zrl);
			return;
		}
			
		$arc = new ZipArchive;
		$x = $arc->open($zfn, ZipArchive::CREATE);
		if(!$x) die("Could not create the zip file.");
	}
	

	for($t = 0; $t <= $end && array_key_exists($t, $tbls); $t++)
	{
		//echo '...' . $cField . "\r\n";
		//print_r($cVals);
		
		if($dataType == "data" && $xml)
		{
			fwrite($fxml, "<table><table_name>{$tbls[$t]}</table_name>");
		}

		for($c = 0; $c < count($cVals) || $c < 1; $c++)
		{

			$res = false;
			
			if($entry && count($cVals) == 0) break;
			$args = array();
			
			if($entry) $args[$cField] = $cVals[$c];
				
			$res = $survey->tables[$tbls[$t]]->ask($args,0,0,'created','asc',true,'object',false);

			if($res !== true) echo $res;
	
			while ($ent = $survey->tables[$tbls[$t]]->recieve(1))
			{
				$ent = $ent[0];
				
				if($dataType == "data")
				{
					
					if($xml)
					{
						fwrite($fxml,"\t\t<entry>\n");
						foreach(array_keys($ent) as $fld)
						{
							if($fld == "childEntries") continue;
							if(array_key_exists($fld, $survey->tables[$tbls[$t]]->fields) && preg_match("/^(gps|location)$/i", $survey->tables[$tbls[$t]]->fields[$fld]->type))
							{
								$gpsObj = $ent[$fld];
								try{
									fwrite($fxml,"\t\t\t<{$fld}_lat>{$gpsObj['latitude']}</{$fld}_lat>\n");
									fwrite($fxml,"\t\t\t<{$fld}_lon>{$gpsObj['longitude']}</{$fld}_lon>\n");
									fwrite($fxml,"\t\t\t<{$fld}_acc>{$gpsObj['accuracy']}</{$fld}_acc>\n");
									if (array_key_exists('provider', $gpsObj)) fwrite($fxml,"\t\t\t<{$fld}_provider>{$gpsObj['provider']}</{$fld}_provider>\n");
									if (array_key_exists('altitude', $gpsObj)) fwrite($fxml,"\t\t\t<{$fld}_alt>{$gpsObj['altitude']}</{$fld}_alt>\n");
									if (array_key_exists('bearing', $gpsObj)) fwrite($fxml,"\t\t\t<{$fld}_bearing>{$gpsObj['bearing']}</{$fld}_bearing>\n");
								}
								catch(ErrorException $e)
								{
									fwrite($fxml,"\t\t\t<{$fld}_lat>0</{$fld}_lat>\n");
									fwrite($fxml,"\t\t\t<{$fld}_lon>0</{$fld}_lon>\n");
									fwrite($fxml,"\t\t\t<{$fld}_acc>-1</{$fld}_acc>\n");
									fwrite($fxml,"\t\t\t<{$fld}_provider>None</{$fld}_provider>\n");
									fwrite($fxml,"\t\t\t<{$fld}_alt>0</{$fld}_alt>\n");
									fwrite($fxml,"\t\t\t<{$fld}_bearing>0</{$fld}_bearing>\n");
									$e = null;
								}
								$gpsObj = null;
							}
							else
							{
								fwrite($fxml,"\t\t\t<$fld>" . str_replace(">", "&gt;", str_replace("<", "&lt;", str_replace("&", "&amp;", $ent[$fld]))) . "</$fld>\n");
							}
						}
						fwrite($fxml, "\t\t</entry>\n");
					}
					else
					{
						fwrite($tsv, "{$tbls[$t]}$delim");
						foreach(array_keys($ent) as $fld)
						{
							if(array_key_exists($fld, $survey->tables[$tbls[$t]]->fields) && preg_match("/^(gps|location)$/i", $survey->tables[$tbls[$t]]->fields[$fld]->type) && $ent[$fld] != "")
							{
								$gpsObj = $ent[$fld];
								fwrite($tsv,"{$fld}_lat{$delim}{$gpsObj['latitude']}{$delim}");
								fwrite($tsv,"{$fld}_lon{$delim}{$gpsObj['longitude']}{$delim}");
								fwrite($tsv,"{$fld}_acc{$delim}{$gpsObj['accuracy']}{$delim}");
								fwrite($tsv,"{$fld}_provider{$delim}{$gpsObj['provider']}{$delim}");
								fwrite($tsv,"{$fld}_alt{$delim}{$gpsObj['altitude']}{$delim}");
								if(array_key_exists('bearing', $gpsObj)) fwrite($tsv,"{$fld}_bearing{$delim}{$gpsObj['bearing']}{$delim}");
								
							}
							else
							{
								fwrite($tsv,  "$fld$delim" . EpiCollectUtils::escapeTSV($ent[$fld]). $delim);
							}
						}
						//fwrite($tsv, $ent);
						fwrite($tsv,  $rowDelim);
						
					}
					
				}
				elseif(strtolower($_GET["type"]) == "thumbnail")
				{
					foreach(array_keys($ent) as $fld)
					{
						if($fld == "childEntries" || !array_key_exists($fld, $survey->tables[$tbls[$t]]->fields)) continue;
						if($survey->tables[$tbls[$t]]->fields[$fld]->type == "photo" && $ent[$fld] != "")// && file_exists("$root\\ec\\uploads\\tn_".$ent[$fld]))
						{
							$fn = "$root\\ec\\uploads\\";
							$bfn = "$root\\ec\\uploads\\" . $ent[$fld];
							if(strstr($ent[$fld], '~tn~'))
							{
								//for images where the value was stored as a thumbnail
								$fn .= $ent[$fld];
							}
							elseif(strstr($ent[$fld], '~'))
							{
								//for images stored as a value with the project name
								$fn .= str_replace('~', '~tn~', $ent[$fld]);
							}
							else
							{
								//otherwise
								$fn .= $survey->name . '~tn~' . $ent[$fld];
							}
							
							if(file_exists($fn))
							{
								if(!$arc->addFile( $fn, $ent[$fld])) die("fail -- " . $fn);
								$files_added++;
							}
							elseif (file_exists($bfn))
							{
								if(!$arc->addFile( $bfn, $ent[$fld])) die("fail -- " . $bfn);
								$files_added++;
							}
						}
					}
				}
				elseif(strtolower($_GET["type"]) == "full_image")
				{
					foreach(array_keys($ent) as $fld)
					{
					if($fld == "childEntries" || !array_key_exists($fld, $survey->tables[$tbls[$t]]->fields)) continue;
						if($survey->tables[$tbls[$t]]->fields[$fld]->type == "photo" && $ent[$fld] != "")// && file_exists("$root\\ec\\uploads\\".$ent[$fld]))
						{
							$fn = "$root\\ec\\uploads\\";
							$bfn = "$root\\ec\\uploads\\" . $ent[$fld];
							if(strstr($ent[$fld], '~tn~'))
							{
								//for images where the value was stored as a thumbnail
								$fn .= str_replace('~tn~', '~', $ent[$fld]);
							}
							elseif(strstr($ent[$fld], '~'))
							{
								//for images stored as a value with the project name
								$fn .=  $ent[$fld];
							}
							else
							{
								//otherwise
								$fn .= $survey->name . '~' . $ent[$fld];
							}
							
							if(file_exists($fn))
							{
								if(!$arc->addFile( $fn, $ent[$fld])) die("fail -- " . $fn);
								$files_added++;
							}
							elseif (file_exists($bfn))
							{
								if(!$arc->addFile( $bfn, $ent[$fld])) die("fail -- " . $bfn);
								$files_added++;
							}
						}
					}
				}
				else
				{
					foreach(array_keys($ent) as $fld)
					{
						if($fld == "childEntries" || !array_key_exists($fld, $survey->tables[$tbls[$t]]->fields)) continue;
						if($survey->tables[$tbls[$t]]->fields[$fld]->type == $_GET["type"] && $ent[$fld] != "" && file_exists("$root\\ec\\uploads\\".$ent[$fld]))
						{
							if(!$arc->addFile( "$root\\ec\\uploads\\" . $ent[$fld], $ent[$fld])) die("fail -- \\ec\\uploads\\" . $ent[$fld]);
							$files_added++;
						}
					}
				}

				if($ent && !array_key_exists($ent[$survey->tables[$tbls[$t]]->key], $nxtCVals))
				{	
					$nxtCVals[$ent[$survey->tables[$tbls[$t]]->key]] = true;
				}
			}
		}
		if($dataType == "data" && $xml)
		{
			fwrite($fxml,  "</table>");
		}

		if($entry)
		{
			$cField = $survey->tables[$tbls[$t]]->key;
			$cVals = array_keys($nxtCVals);
			$nxtCVals = array();
		}
	}

	if($dataType == "data" && $xml)
	{
		fwrite($fxml,  "</entries>");
		fclose($fxml);
		EpiCollectWebApp::Redirect($fx_url);
		return;
		//echo file_get_contents($fxn);
	}
	elseif ($dataType == "data")
	{
		fclose($tsv);
		EpiCollectWebApp::Redirect($ts_url);
		return;
		//echo file_get_contents($txn);
	}
	else
	{
		//close zip files
		$err = $arc->close();
		if($files_added === 0)
		{
			echo "no files";
			return;
		}
			
		if(!$err == true) {
			echo "fail expecting $files_added files";
			return;
		}

		EpiCollectWebApp::Redirect($zrl);
		return;
	}
}




function entryHandler()
{
	global $auth, $url, $log, $SITE_ROOT;

	EpiCollectWebApp::DoNotCache();

	$prjEnd = strpos($url, "/");
	$frmEnd =  strpos($url, "/", $prjEnd+1);
	$prjName = substr($url,0,$prjEnd);
	$frmName = substr($url,$prjEnd + 1,$frmEnd - $prjEnd - 1);
	$entId = urldecode(substr($url, $frmEnd + 1));

	$prj = new EcProject();
	$prj->name = $prjName;
	$prj->fetch();

	$permissionLevel = 0;
	$loggedIn = $auth->isLoggedIn();
	
	if($loggedIn) $permissionLevel = $prj->checkPermission($auth->getEcUserId());
	
	$ent = new EcEntry($prj->tables[$frmName]);
	$ent->key = $entId;
	$r = $ent->fetch();


	if($_SERVER["REQUEST_METHOD"] == "DELETE")
	{
		if($permissionLevel < 2)
		{
			EpiCollectWebApp::Denied('delete entries on this project');
			return;
		}
		
		if($r === true)
		{
			try
			{
				$ent->delete();
			}
			catch(Exception $e)
			{
				if(preg_match("/^Message\s?:/", $e->getMessage()))
				{
                                    EpiCollectWebApp::BadRequest('conflict');
				}
				else
				{
                                    EpiCollectWebApp::Fail();
				}
				echo $e->getMessage();
			}
		}
		else
		{
			echo $r;
		}
	}
	else if($_SERVER["REQUEST_METHOD"] == "PUT")
	{
		if($permissionLevel < 2)
		{
			EpiCollect::Denied(' edit entries for this project');
		}
		
		if($r === true)
		{
			$request_vars = array();
			parse_str(file_get_contents("php://input"), $request_vars);

			foreach($request_vars as $key => $value)
			{
				if(array_key_exists($key, $prj->tables[$frmName]->fields))
				{
					$ent->values[$key] = $value;
				}
			}

			$r = $ent->put();
			if($r !== true)
			{ 
				echo "{ \"false\" : true, \"msg\" : \"$r\"}";
			}
			else 
			{
				echo "{ \"success\" : true, \"msg\" : \"\"}";
			}
		}
		else{
			echo "{ \"success\" : false, \"msg\" : \"$r\"";
		}
	}
	else if($_SERVER["REQUEST_METHOD"] == "GET")
	{
		$val = EpiCollectUtils::array_get_if_exists($_GET, 'term');
		$do  = EpiCollectUtils::array_get_if_exists($_GET, 'validate');
		$key_from = EpiCollectUtils::array_get_if_exists($_GET, 'key_from');
		$secondary_field = EpiCollectUtils::array_get_if_exists($_GET, 'secondary_field');
		$secondary_value = EpiCollectUtils::array_get_if_exists($_GET, 'secondary_value');
		ini_set('max_execution_time', 60);
		if($entId == 'title')
		{
			if($do)
			{
				echo $prj->tables[$frmName]->validateTitle($val, $secondary_field, $secondary_value);				
			}
			elseif($key_from)
			{

				echo $prj->tables[$frmName]->getTitleFromKey($val);
			}
			else
			{
				
				echo $prj->tables[$frmName]->autoCompleteTitle($val, $secondary_field, $secondary_value);
			}
		}
		elseif($do)
		{
			echo $prj->tables[$frmName]->validate($entId, $val);
		}
		else
		{
			echo $prj->tables[$frmName]->autoComplete($entId, $val);
		}
	}
}


function updateUser()
{
	global $auth;
	
	if($_SERVER["REQUEST_METHOD"] == "POST")
	{
		$pwd = EpiCollectUtils::array_get_if_exists($_POST, "password");
		$con = EpiCollectUtils::array_get_if_exists($_POST, "confirmpassword");
		
		$change = true;
		
		if(!$pwd || !$con)
		{
			$change = false;
			EpiCollectWebApp::flash("Password not changed, password was blank.", "err");
		}
		
		if($pwd != $con)
		{
			$change = false;
			EpiCollectWebApp::flash("Password not changed, passwords did not match.", "err");
		}
		
		
		if(strlen($pwd) < 8) {
			$change = false;
			EpiCollectWebApp::flash("Password not changed, password was shorter than 8 characters.", "err");
		}
		
		if(!preg_match("/^.*(?=.{8,})(?=.*\d)(?=.*[a-zA-Z]).*$/", $pwd))
		{
			$change = false;
			EpiCollectWebApp::flash("Password not changed, password must be longer than 8 characters and contain at least one letter and at least one number.", "err");
		}
		
		if($auth->setPassword($auth->getEcUserId(), $_POST["password"]))
		{
			EpiCollectWebApp::flash("Password changed");
		}else {
			EpiCollectWebApp::flash("Password not changed.", "err");
		}
	}
	
	$name = explode(" ", $auth->getUserNickname());
	
	$username = $auth->getUserName();
	$is_not_local = $_SESSION['provider'] != 'LOCAL';
	
	if($is_not_local) EpiCollectWebApp::flash('You cannot update user information for Open ID or LDAP users unless you do it throught your Open ID or LDAP provider','err');
		
	echo applyTemplate("base.html", "./updateUser.html", array(
			"firstName" => $name[0], 
			"lastName" => $name[1],
			"email" => $auth->getUserEmail(),
			"userName" => $username,
			"disabled" => $is_not_local ? 'disabled="disabled"' : ''
	));
}

function saveUser()
{
	global $auth, $db;
	$qry = "CALL updateUser(" . $auth->getEcUserId() . ",'{$_POST["name"]}','{$_POST["email"]}')";
	$res = $db->do_query($qry);

	if($res === true)
	{
		echo '{"success" : true, "msg" : "User updated successfully"}';
	}
	else
	{
		echo '{"success" : false, "msg" : "'.$res.'"}';
	}
}

function uploadProjectXML()
{
	global $SITE_ROOT;

	$prj = new EcProject();

	if(!file_exists("ec/xml")) mkdir("ec/xml");

	$newfn = "ec/xml/" . $_FILES["projectXML"]["name"];
	move_uploaded_file($_FILES["projectXML"]["tmp_name"], $newfn);
	$prj->parse(file_get_contents($newfn));

	$res = $prj->post();
	if($res === true)
	{
		$server = trim($_SERVER["HTTP_HOST"], "/");
		$root = trim($SITE_ROOT, "/");
		EpiCollectWebApp::Redirect("http://$server/$root/editProject.html?name={$prj->name}");
		return;
	}
	else
	{
		$vals = array("error" => $res);
		echo applyTemplate("base.html","./error.html",$vals);
	}
}



function updateXML()
{
	global $url, $SITE_ROOT;

	$xml = '';
	if(array_key_exists("xml", $_REQUEST) && trim($_REQUEST['xml']) != '')
	{
		$xml = file_get_contents("ec/xml/{$_REQUEST["xml"]}");
	}
	elseif(array_key_exists("data", $_POST) && $_POST["data"] != '')
	{
		$xml = $_POST["data"];	
	}
	else 
	{
		$xml = false;
	}
		
	$prj = new EcProject();
	$prj->name = substr($url, 0, strpos($url, "/"));
	$prj->fetch();
	
	//echo '--', $xml , '--';
	if($xml)
	{
		$n = '';
		$validation = validate(NULL,$xml, $n, true, true);
		if($validation !== true)
		{
			echo "{ \"result\": false , \"message\" : \"" . $validation . "\" }";
			return;
		}
		unset($validation);
		
		foreach($prj->tables as $name => $tbl)
		{
			foreach($prj->tables[$name]->fields as $fldname => $fld)
			{
				$prj->tables[$name]->fields[$fldname]->active = false;
			}
		}
		try 
		{
			$prj->parse($xml);
			
		}catch(Exception $err)
		{
			echo "{ \"result\": false , \"message\" : \"" . $err->getMessage() . "\" }";
			return;
		}
		
		$prj->publicSubmission = true;
	}

	if(!EpiCollectUtils::array_get_if_exists($_POST, "skipdesc"))
	{	
		$prj->description = EpiCollectUtils::array_get_if_exists($_POST, "description");
		$prj->image = EpiCollectUtils::array_get_if_exists($_POST, "projectImage");
	}
	
	if(array_key_exists("listed", $_REQUEST)) $prj->isListed = $_REQUEST["listed"] == "true";
	if(array_key_exists("public", $_REQUEST)) $prj->isPublic = $_REQUEST["public"] == "true";
	$res = $prj->put($prj->name);
	if($res !== true) die($res);
	if(array_key_exists("managers", $_POST)) $prj->setManagers($_POST["managers"]);
	if(array_key_exists("curators", $_POST)) $prj->setCurators($_POST["curators"]);
	// TODO : add submitter $prj->setProjectPermissions($submitters,1);

	if($res === true)
	{
		$server = trim($_SERVER["HTTP_HOST"], "/");
		$root = trim($SITE_ROOT, "/");
		
		echo "{ \"result\": true }";
	}
	else
	{
		echo "{ \"result\": false , \"message\" : \"$res\" }";
	}
}

function tableStats()
{
	global  $url, $log;
	ini_set('max_execution_time', 60);
	EpiCollectWebApp::DoNotCache();

	$prjEnd = strpos($url, "/");
	$frmEnd =  strpos($url, "/", $prjEnd+1);
	$prjName = substr($url,0,$prjEnd);
	$frmName = substr($url,$prjEnd + 1,$frmEnd - $prjEnd - 1);

	$prj = new EcProject();
	$prj->name = $prjName;
	$prj->fetch();
	echo json_encode($prj->tables[$frmName]->getSummary($_GET));
}

function listXml()
{
	//List XML files
	if(!file_exists("ec/xml")) mkdir("ec/xml");
	$h = opendir("ec/xml");
	$tbl =  "<table id=\"projectTable\"><tr><th>File</th><th>Validation Result</th><th>Create</th><td>&nbsp;</td></tr>";
	$n = "";
	while($fn = readdir($h))
	{
		if(!preg_match("/^\.|.*\.xsd$/", $fn))
		{
			$e = false;
			$v = validate($fn, NULL, $n);
			if($v === true)
			{
				$p = new EcProject;
				$p->name = $n;
				$res = $p->fetch();
				if($res !== true) echo $res;
				$e = count($p->tables) > 0;
			}

			$tbl .= "<tr id=\"{$n}row\"><td>$fn</td><td>" . ($v === true ? "$n - <span class=\"success\" >Valid</span>" : "$n - <span class=\"failure\" >Invalid</span> <a href=\"javascript:expand('{$n}res', '{$n}row')\">Show errors</a><div id=\"{$n}res\" class=\"verrors\">$v</div>") . ($e === true ?  "</td><td>Project already exists : <a class=\"button\" href=\"$n\">homepage</a></td><td>&nbsp;</td></tr>" : ($v ===true ? "</td><td><a class=\"button\" href=\"create?xml=$fn\">Create Project</a></td><td>&nbsp;</td></tr>" : "</td><td></td><td>&nbsp;</td></tr>"));
		}
	}
	$tbl.= "</table>";
	return $tbl;
	//DONE!: for each get the project name and work out if the project exists.
}

function projectCreator()
{
	if(!file_exists("ec/xml")) mkdir("ec/xml");
	
	if(array_key_exists("xml", $_FILES))
	{
		move_uploaded_file($_FILES["xml"]["tmp_name"], "ec/xml/{$_FILES["xml"]["name"]}");
	}
	if(EpiCollectUtils::array_get_if_exists($_REQUEST, "json"))
	{
		$n = '';
		echo validate("{$_FILES["xml"]["name"]}", NULL, $n, EpiCollectUtils::array_get_if_exists($_POST, 'update'));
	}
	else
	{
		$vals = array();
		$vals["xmlFolder"] = getcwd() . "/xml";
		$vals["projects"] = listXML();
		echo applyTemplate("base.html","create.html", $vals);
	}
}

function validate($fn = NULL, $xml = NULL, &$name = NULL, $update = false, $returnJson = false)
{
	global $SITE_ROOT;

	$isValid = true;
	$msgs = array();

	if(!$fn) $fn = EpiCollectUtils::array_get_if_exists($_GET, "filename");
	
	if($fn && !$xml)
	{		

		$xml = file_get_contents("./ec/xml/$fn");
	}

	$prj = new EcProject;
	try{
		$prj->parse($xml);
	}
	catch(Exception $err)
	{
		array_push($msgs, "The XML for this project is invalid : " . $err->getMessage());
	}
	
	if(count($msgs) == 0)
	{	
		$prj->name = trim($prj->name);
		
		if(!$update && EcProject::projectExists($prj->name))
		{
			array_push($msgs, sprintf('A project called %s already exists.', $prj->name));
		}
		
		if(!$prj->name || $prj->name == "")
		{
			array_push($msgs, "This project does not have a name, please include a projectName attribute in the model tag.");
		}
	
		if(!$prj->ecVersionNumber || $prj->ecVersionNumber == "")
		{
			array_push($msgs, "Projects must specify a version");
		}
	
		if(count($prj->tables) == 0) array_push($msgs, "A project must contain at least one table.");
	
		foreach($prj->tables as $tbl)
		{
			if($tbl->number <= 0) continue;
			if(!$tbl->name || $tbl->name == "") array_push($msgs, "Each form must have a name.");
			if(!$tbl->key || $tbl->key == "")
			{
				array_push($msgs, "Each form must have a unique key field.");
			}
			elseif(!$tbl->fields[$tbl->key])
			{
				array_push($msgs, "The form {$tbl->name} does not have a field called {$tbl->key}, please specify another key field.");
			}
			elseif(!preg_match("/input|barcode/", $tbl->fields[$tbl->key]->type))
			{
				array_push($msgs, "The field {$tbl->key} in the form {$tbl->name} is a {$tbl->fields[$tbl->key]->type} field. All key fields must be either text inputs or barcodes.");
			}
				
			//array_push($msgs, "<b>$tbl->name</b>");
			foreach($tbl->fields as $fld)
			{
				if(preg_match("/^[0-9]/", $fld->name) || $fld->name == '')
				{
					$isValid = false;
					array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has an invalid name, field names cannot start with a number");
				}
				if(!$fld->label || $fld->label == '')
				{
					$isValid = false;
					array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has no label. All fields must have a label and the label must not be null. If you have added a label to the field please make sure the tags are all in lower case i.e. <label>...</label> not <Label>...</Label>");
				}
	
				if($fld->jump)
				{
					//break the jump up into it's parts
					$jBits = explode(",", $fld->jump);
					if(count($jBits) % 2 != 0)
					{
						$isValid = false;
						array_push($msgs, "The field called {$fld->name} in the form {$tbl->name} has an invalid jump attribute. All jumps should be in the format value,target");
					}
						
					for($i = 0; $i + 1 < count($jBits); $i += 2)
					{
						$jBits[$i] = trim($jBits[$i]);
						$jBits[$i + 1] = trim($jBits[$i + 1]);
						//check that the jump destination exists in the current form
						if(!preg_match( '/END/i', $jBits[$i]) && !array_key_exists($jBits[$i], $tbl->fields))
						{
							$isValid = false;
							array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has an invalid jump statement the field {$jBits[$i]} that is the target when the value is {$jBits[$i+1]} does not exist in this form");
						}
						//check that the jump value exists in the form
						if( $fld->type == "select1" || $fld->type == "radio")
						{
							$tval = preg_replace('/^!/', '',$jBits[$i + 1]);
							if(!($jBits[$i + 1] == "all" ||  (preg_match('/^[0-9]+$/',$tval) && (intval($tval) <= count($fld->options)) && intval($tval) > 0)))
							{
								$isValid = false;
								array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has an invalid jump statement the jump to {$jBits[$i]} is set to happen when {$jBits[$i+1]}. If the field type is {$fld->type} the target must be between 1 and " . (count($fld->options)) . " for this field options the criteria must be a valid index of an element or 'all'");
							}
						}
						elseif($fld->type == "select")
						{
							$found = false;
							for($o = 0; $o < count($fld->options); $o++)
							{
								if(preg_match("/^!?" . $fld->options[$o]->value."$/", $jBits[$i +1 ]))
								{
									$found = true;
									break;
								}
							}
							if(!$found)
							{
								$isValid = false;
								array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has an invalid jump statement the jump to {$jBits[$i]} is set to happen when this field is {$jBits[$i+1]}. This value does not exist as an option.");
							}
						}
						elseif($fld->type == 'numeric')
						{
							if(!preg_match('/NULL|all/i', $jBits[$i+1]))
							{
								$v = intval($jBits[$i+1], 10);
								if($fld->max && $v > $fld->max)
								{
									$isValid = false;
									array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has an invalid jump statement, the jump value exceeds the fields maximum;");
								}
								if($fld->min && $v < $fld->min)
								{
									$isValid = false;
									array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has an invalid jump statement, the jump value is less than the fields maximum;");
								}
							}
						}
					
					}
				}
				if($fld->type == "group")
				{
					//make sure the group form exists
					if(!$fld->group_form)
					{
						$isValid = false;
						array_push($msgs, "The field {$fld->name} is a group form but has no group attribute.");
					}
					/*elseif(!array_key_exists($fld->group_form, $prj->tables))
					{
						$isValid = false;
						array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has the form {$fld->group_form} set as it's group form, but the form {$fld->group_form} doesn not exist.");
					}*/
				}
				if($fld->type == "branch")
				{
					//make sure the branch form exists
					if(!array_key_exists($fld->branch_form, $prj->tables))
					{
						$isValid = false;
						array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has the form {$fld->branch_form} set as it's branch form, but the form {$fld->branch_form} doesn not exist.");
					}
				}
				if($fld->regex)
				{
					//make sure the REGEX is a valid Regex
					try
					{
						preg_match("/" . $fld->regex . "/", "12345");
					}
					catch(Exception $err)
					{
						array_push($msgs, "The field {$fld->name} in the form {$tbl->name} has an invalid regular expression in it's regex attribute \"($fld->regex)\".");
					}		
				}
			}
		}
		$name = $prj->name;
	}
	
	if( $returnJson )
	{
		return count($msgs) == 0 ? true : str_replace('"', '\"', implode("\",\"", $msgs));
	}
	elseif( EpiCollectUtils::array_get_if_exists($_REQUEST, "json") )
	{
		echo "{\"valid\" : " . (count($msgs) == 0 ? "true" : "false") . ", \"msgs\" : [ \"" . str_replace('"', '\"', implode("\",\"", $msgs))  . "\" ], \"name\" : \"$name\", \"file\" :\"$fn\" }";
	}
	else
	{
		return count($msgs) == 0 ? true : "<ol><li>" . str_replace('"', '\"', implode("</li><li>", $msgs)) . "</li></ol>";
	}
}

function admin()
{

	global $auth, $SITE_ROOT, $cfg;

	if(count($auth->getServerManagers()) > 0 && $auth->isLoggedIn() && !$auth->isServerManager())
	{
		EpiCollectWebApp::flash("Configuration only available to server managers", "err");
			
		EpiCollectWebApp::Redirect($SITE_ROOT);
		return;
	}

	if($_SERVER["REQUEST_METHOD"] == "GET")
	{
		$mans = $auth->getServerManagers();
		$men = "";
		foreach($mans as $man)
		{
			$men .= "<form method=\"POST\" action=\"user/manager\"><p>{$man["firstName"]} {$man["lastName"]} ({$man["Email"]})<input type=\"hidden\" name=\"email\" value=\"{$man["Email"]}\" />" .($auth->getUserEmail() == $man["Email"] ? "" : "<input type=\"submit\" name=\"remove\" value=\"Remove\" />" ) ."</form></p>";
		}
			
		$arr = "{";
		foreach($cfg->settings as $k => $v)
		{
			foreach($v as $sk => $sv)
			{
				$arr .= "\"{$k}\\\\{$sk}\" : \"$sv\",";
			}
		}
		$arr = trim($arr, ",") . "}";
			
		echo applyTemplate("./base.html", "./admin.html", array("serverManagers" => $men, "vals" => $arr));

	}
	else if($_SERVER["REQUEST_METHOD"] == "POST")
	{
		createUser();
	}
}

function createUser()
{
	global $auth, $SITE_ROOT, $cfg;

	EpiCollectWebApp::DoNotCache();

	if($cfg->settings["security"]["use_local"] != "true")
	{
		EpiCollectWebApp::flash("This server is not configured to user Local Accounts", "err");
	}
	elseif($auth->createUser($_POST["username"], $_POST["password"], $_POST["email"], $_POST["fname"], $_POST["lname"],"en"))
	{
		EpiCollectWebApp::flash("User Added");
	}
	else
	{
		EpiCollectWebApp::flash("Could not create the user", "err");
	}

	EpiCollectWebApp::Redirect("http://{$_SERVER["HTTP_HOST"]}$SITE_ROOT/admin");
	return;
}

function managerHandler()
{
	global $auth, $SITE_ROOT;

	if($_SERVER["REQUEST_METHOD"] == "POST")
	{
		if(array_key_exists("remove", $_POST) && $_POST["remove"] == "Remove")
		{
			$auth->removeServerManager($_POST["email"]);
			EpiCollectWebApp::flash("{$_POST["email"]} is no longer a server manager.");
		}
		else
		{
			$x = $auth->makeServerManager($_POST["email"]);
			if($x === 1)
			{
				EpiCollectWebApp::flash("{$_POST["email"]} is now a server manager.");
			}
			elseif ($x === -1)
			{
				EpiCollectWebApp::flash("{$_POST["email"]} is already a server manager.");
			}
			else
			{
				EpiCollectWebApp::flash("Could not find user {$_POST["email"]}. ($x)", "err");
			}
		}
	}


	EpiCollectWebApp::Redirect("http://{$_SERVER["HTTP_HOST"]}{$SITE_ROOT}/admin#manage");
	return;
}



function updateProject()
{
	global  $url, $auth, $db;

	$pNameEnd = strrpos($url, "/");
	$oldName = substr($url, 0, $pNameEnd);
	$prj = new EcProject();
	$prj->name = $oldName;
	$prj->fetch();
	
	$role = intVal($prj->checkPermission($auth->getEcUserId()));
	
         EpiCollectWebApp::DoNotCache();
	if($role != 3)
	{
		
           
		flash ("You do not have permission to manage this project", "err");
		$url = str_replace("update", "", $url);
		EpiCollectWebApp::Redirect("{$SITE_ROOT}/$url");
	}
	else
	{
		
		if($_SERVER["REQUEST_METHOD"] == "POST")
		{
			$xml = EpiCollectUtils::array_get_if_exists($_POST, "xml");
			$managers = EpiCollectUtils::array_get_if_exists($_POST, "managers");
			$curators = EpiCollectUtils::array_get_if_exists($_POST, "curators");
			$public = EpiCollectUtils::array_get_if_exists($_POST, "public");
			$listed = EpiCollectUtils::array_get_if_exists($_POST, "listed");

			
			
			$drty = false;
			if($xml && $xml != "")
			{
				$prj->parse($xml);
				if($prj->name != oldName) 
				{
                                    EpiCollectWebApp::BadRequest("CANNOT CHANGE NAME");
					return false;
				}
				$drty = true;
			}
			
			echo 'description ' . $prj->description . ' ' .EpiCollectUtils::array_get_if_exists($_POST, "description") ;
			if($prj->description != EpiCollectUtils::array_get_if_exists($_POST, "description"))
			{
				
				$prj->description = EpiCollectUtils::array_get_if_exists($_POST, "description");
				$drty = true;
			}
			if($prj->image != EpiCollectUtils::array_get_if_exists($_POST, "projectImage"))
			{
				$prj->image = EpiCollectUtils::array_get_if_exists($_POST, "projectImage");
				$drty = true;
			}
			
			if($public !== false)
			{
				$prj->isPublic = $public === "true";
				$drty = true;
			}
			if($listed !== false)
			{
				$prj->isListed = $listed === "true";
				$drty = true;
			}
			if($drty)
			{
				$prj->publicSubmission = true;
				$prj->put($oldName);
			}
			if($curators) $prj->setCurators($curators);
			if($managers) $prj->setManagers($managers);
				
		}
		else
		{
			$managers = $prj->getManagers();
			if(is_array($managers))
			{
				$managers = '"' . implode(",", $managers) . '"';
			}else{
				$curators = '""';
			}

			$curators = $prj->getCurators();
			if(is_array($curators))
			{
				$curators = '"' . implode(",", $curators) . '"';
			}else{
				$curators = '""';
			}

			$img = $prj->image;
			$img = substr($img, strpos($img, '~') + 1);
			
			echo applyTemplate("./base.html", "./updateProject.html", array("projectName" => $prj->name, "description" => $prj->description, "image" => $img, "managers" => $managers, "curators" => $curators, "public" => $prj->isPublic, "listed" => $prj->isListed ));
			return;
		}
	}
}


function formBuilder()
{
	global $url, $auth;
	$prj_name = str_replace('/formBuilder', '', $url);
	
        $prj = new EcProject();
        $prj->name = $prj_name;
        $prj->fetch();
        
        $uid = $auth->getEcUserId();
        
        if($prj->checkPermission($uid))
        {
            echo applyTemplate('./project_base.html' , './createOrEditForm.html', array('projectName' => $prj_name));
        }
        else
        {
            accessDenied(sprintf(' Project %s' , $prj_name ));
        }
}

function getControlTypes()
{
	global $db;
	//$db = new dbConnection();
	$res = $db->do_query('SELECT * FROM FieldType');

	if($res === true)
	{
		$arr = array();
		while ($a = $db->get_row_array())
		{
			array_push($arr, $a);
		}
			
		EpiCollectWebApp::ContentType('json');
		echo json_encode(array("controlTypes" => $arr));
	}
}

function uploadMedia()
{
	global $url, $SITE_ROOT;
	$pNameEnd = strpos($url, "/");
	$pname = substr($url, 0, $pNameEnd);
	$extStart = strpos($url, ".");
	$fNameEnd = strpos($url, "/", $pNameEnd + 1);
	$frmName = rtrim(substr($url, $pNameEnd + 1, $fNameEnd - $pNameEnd), "/");

	if($frmName == 'uploadMedia') $frmName = false;
	
	$tvals = array("project" => $pname,"form" => $frmName);

	if(!file_exists('ec/uploads')) mkdir('ec/uploads');
	
	if(array_key_exists("newfile", $_FILES) && $_FILES["newfile"]["error"] == 0)
	{
		if(preg_match("/\.(png|gif|jpe?g|bmp|tiff?)$/", $_FILES["newfile"]["name"]))
		{
			$fn = "ec/uploads/{$pname}~".$_FILES["newfile"]["name"];
			move_uploaded_file($_FILES["newfile"]["tmp_name"], $fn);

			$tnfn = str_replace("~", "~tn~", $fn);

			$imgSize = getimagesize($fn);

			$scl = min(384/$imgSize[0], 512/$imgSize[1]);
			$nw = $imgSize[0] * $scl;$nh =  $imgSize[1] * $scl;

			if(preg_match("/\.jpe?g$/", $fn))
			{
				$img = imagecreatefromjpeg($fn);
			}
			elseif(preg_match("/\.gif$/", $fn))
			{
				$img = imagecreatefromgif($fn);
			}
			elseif(preg_match("/\.png$/", $fn))
			{
				$img = imagecreatefrompng($fn);
				imagealphablending($img, true); // setting alpha blending on
				imagesavealpha($img, true); // save alphablending setting (important)
			}
			else
			{
				echo "not supported";
				return;
			}

			$thn = imagecreatetruecolor($nw,$nh);
			imagecopyresampled($thn, $img, 0,0, 0,0, $nw, $nh, $imgSize[0], $imgSize[1]);


			if(preg_match("/\.jpe?g$/", $fn))
			{
				imagejpeg($thn, $tnfn, 95);
			}
			elseif(preg_match("/\.gif$/", $fn))
			{
				imagegif($thn, $tnfn);
			}
			elseif(preg_match("/\.png$/", $fn))
			{
				imagepng($thn, $tnfn);
			}

			$tvals["mediaTag"] = "<img src=\"$SITE_ROOT/{$tnfn}\" />";
		}
		elseif(preg_match("/\.(mov|wav|mpe?g?[34]|ogg|ogv)$/", $_FILES["newfile"]["name"]))
		{
			//audio/video handler
			$fn = "ec/uploads/{$pname}~".$_FILES["newfile"]["name"];
			move_uploaded_file($_FILES["newfile"]["tmp_name"], $fn);

			$tvals["mediaTag"] = "<a href=\"$SITE_ROOT/{$pname}~{$fn}\" >View File</a>";
		}
		else
		{
			echo "not supported";
			return;
		}
		
	}

	if(array_key_exists("fn", $_GET))
	{
		$fn = "ec/uploads/{$pname}~".$_GET["fn"];
		$tvals["mediaTag"] = "<img src=\"$SITE_ROOT/{$fn}\" height=\"150\" />";
		$tvals["fn"] = str_replace("ec/uploads/", "", $fn);
	}

	echo applyTemplate("./uploadIFrame.html", "./base.html", $tvals);
}

function getMedia()
{
	global $url;
	
	if(preg_match('~tn~', $url) )
	{
		//if the image is a thumbnail just try and open it
		EpiCollectWebApp::ContentType($url);
		echo file_get_contents("./" . $url);
	}
	else
	{
		if(file_exists("./$url"))
		{
			EpiCollectWebApp::ContentType($url);
			echo file_get_contents("./" . $url);
		}
		elseif(file_exists('./'. str_replace("~", "~tn~", $url)))
		{
			$u = str_replace("~", "~tn~", $url);
			EpiCollectWebApp::ContentType($url);
			echo file_get_contents("./" . $u);
		}
		elseif(file_exists('./'. substr($url, strpos($url, '~'))))
		{
			$u = substr($url, strpos($url, '~'));
			EpiCollectWebApp::ContentType($u);
			echo file_get_contents("./" . $u);
		}
		else
		{
			EpiCollectWebApp::NotFound('The file you were looking for ');
			return;
		}
	}
}



function getXML()
{
	if(array_key_exists('name', $_GET))
	{
		$prj = new EcProject();
		$prj->name = $_GET["name"];
		$prj->fetch();
		//print_r($prj);
		echo $prj->toXML();
			
	}
}

function projectSummary()
{
	global $url;

	$prj = new EcProject();
	$prj->name = substr($url, 0, strpos($url, "/"));
	$prj->fetch();
	$sum = $prj->getSummary();

	echo "{\"forms\" : ". json_encode($sum) . "}";
}

function projectUsage()
{
	global $url, $auth;

	$prj = new EcProject();
	$prj->name = substr($url, 0, strpos($url, "/"));
	$prj->fetch();

	if(!$prj->isPublic && $prj->checkPermission($auth->getEcUserId()) < 2) return "access denied";

	$sum = $prj->getUsage();
	EpiCollectWebApp::ContentType('plain');
	echo $sum; //"{\"forms\" : ". json_encode($sum) . "}";
}

function writeSettings()
{
	global $cfg, $SITE_ROOT;
	foreach ($_POST as $k => $v)
	{
		$kp = explode("\\", $k);
		if(count($kp) > 1)
		$cfg->settings[$kp[0]][$kp[1]] = $v;
	}

	if(!array_key_exists("salt",$cfg->settings["security"]) || $cfg->settings["security"]["salt"] == "")
	{
		$str = "./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$str = str_shuffle($str);
		$str = substr($str, -22);
		$cfg->settings["security"]["salt"] = $str;
	}
		
	$cfg->writeConfig();
	EpiCollectWebApp::DoNotCache();
	if(EpiCollectUtils::array_get_if_exists($_POST, "edit"))
	{
		EpiCollectWebApp::Redirect("$SITE_ROOT/admin");
	}
	else
	{
		EpiCollectWebApp::Redirect("$SITE_ROOT/test");
	}
}

function packFiles($files)
{
	if(!is_array($files)) throw new Exception("files to be packed must be an array");

	$str = "";

	foreach($files as $k => $f)
	{
		$str .= file_get_contents($f);
		$str .= "\r\n";
	}

	return $str;
}

function listUsers()
{
	global $auth, $url;
	
	if($auth->isLoggedIn())
	{
		if($auth->isServerManager())
		{
                    EpiCollectWebApp::DoNotCache();
                    EpiCollectWebApp::ContentType('json');
			
			echo "{\"users\":[";
			$usrs = $auth->getUsers();
			for($i = 0; $i < count($usrs); $i++)
			{
				if($i > 0) echo ",";
				echo "{
					\"userId\" : \"{$usrs[$i]["userId"]}\",
					\"firstName\" : \"{$usrs[$i]["FirstName"]}\",
					\"lastName\" : \"{$usrs[$i]["LastName"]}\",
					\"email\" : \"{$usrs[$i]["Email"]}\",
					\"active\" : {$usrs[$i]["active"]}
				}";
			}
			echo "]}";
		}
		else
		{
			echo applyTemplate("./base.html", "./error.html", array("errorType" => 403, "error" => "Permission denied"));
		}
	}
	else
	{
		loginHandler($url);
	}
}

function enableUser()
{
	global $auth;
	
	$user = EpiCollectUtils::array_get_if_exists($_POST, "user");
	
	if($auth->isLoggedIn() && $auth->isServerManager() && $_SERVER["REQUEST_METHOD"] == "POST" && $user)
	{
		EpiCollectWebApp::DoNotCache();
                EpiCollectWebApp::ContentType('json');
                
		$res = $auth->setEnabled($user, true);
		if($res === true)
		{
			
			echo "{\"result\" : true}";
		}
		else
		{
			echo $res;
			echo "{\"result\" : false}";
		}
	}
	else
	{
            EpiCollectWebApp::Denied(' this URL');
	}
}

function disableUser()
{
	global $auth;
	
	$user = EpiCollectUtils::array_get_if_exists($_POST, "user");
	
	if($auth->isLoggedIn() && $auth->isServerManager() && $_SERVER["REQUEST_METHOD"] == "POST" && $user)
	{
		EpiCollectWebApp::DoNotCache();
                EpiCollectWebApp::ContentType('json');
                 
		if($auth->setEnabled($user, false))
		{
			
			echo "{\"result\" : true}";
		}
		else
		{
			echo "{\"result\" : false}";
		}
	}
	else
	{
            EpiCollectWebApp::Denied(' this URL');
	}
}

function resetPassword()
{
	global $auth;
	
	$user = EpiCollectUtils::array_get_if_exists($_POST, "user");
	
	if($auth->isLoggedIn() && $auth->isServerManager() && $_SERVER["REQUEST_METHOD"] == "POST" && $user && preg_match("/[0-9]+/", $user))
	{
		$res = $auth->resetPassword($user);
		
		EpiCollectWebApp::DoNotCache();
                    EpiCollectWebApp::ContentType('json');
		echo "{\"result\" : \"$res\"}";
		
	}
	else
	{
            EpiCollectWebApp::Denied(' this URL');
	}
}

function userHandler()
{
	global $url;

	//if(!(strstr($_SERVER["HTTP_REFERER"], "/createProject.html"))) return;

	$qry = str_replace("user/", "", $url);

	//$db = new dbConnection();
	global $db;
	$sql = "Select details from user where Email = '$qry'";

	$res = $db->do_query($sql);
	if($res === true)
	{
                $arr = $db->get_row_array();
		if($arr)
		{
			if(array_key_exists("details", $arr))
			{
				echo "true";
				return;
			}
			else
			{
				print_r($arr);
			}
		}
		else
		{
			echo "false";
		}
	}
	else
	{
		die($res + " " + $sql);
	}
}
/* end handlers */

/*
 * The page rules array defines how to handle certain urls, if a page rule
* hasn't been defined then then the script should return a 404 error (this
* is in order to protect files that should not be open to public view such
* as log files which may contain restricted data)
*/





//$d = new DateTime();
//$i = $dat->format("su") - $d->format("su");


/*Cookie policy handler*/

//if(!EpiCollectUtils::array_get_if_exists($_SESSION, 'SEEN_COOKIE_MSG')) {
//	EpiCollectWebApp::flash(sprintf('EpiCollectPlus uses cookies make the site work. If you are concerned about our use of cookies please read our <a href="%s/privacy.html">Privacy Statement</a>', $SITE_ROOT));
//	$_SESSION['SEEN_COOKIE_MSG'] = true;
//}
?>
