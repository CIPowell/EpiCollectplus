<?php
//To intercept malicious population of the session superglobal
if (isset($_REQUEST['_SESSION'])) throw new Exception('Bad client request');

include ('./utils/HttpUtils.php');
include ('./Auth/AuthManager.php');
include ('./db/dbConnection.php');
include ('./utils/Encoding.php');
include ('./utils/C.php');
include ('./Classes/PageSettings.php');
include ('./Classes/configManager.php');
include ('./Classes/Logger.php');
include('./Classes/EcProject.php');
include('./Classes/EcTable.php');
include('./Classes/EcField.php');
include ('./Classes/EcOption.php');
include ('./Classes/EcEntry.php');
include('./EpiCollect.php');

//This is here as it may not be required during unit testing.
function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
{
	// error was suppressed with the @-operator
	if (0 === error_reporting()) {
		return false;
	}

	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler('handleError', E_ALL);

$app = new EpiCollectWebApp();
$app->before_request();
$app->processRequest();


?>
