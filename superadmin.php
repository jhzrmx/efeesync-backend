<?php

define('ADMIN_USERNAME','efeesync');		// Admin Username
define('ADMIN_PASSWORD','1111');			// Admin Password

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_USER'] != ADMIN_USERNAME ||$_SERVER['PHP_AUTH_PW'] != ADMIN_PASSWORD) {
	header("WWW-Authenticate: Basic realm=\"Efeesync Login (Username: ".ADMIN_USERNAME."/Password: ".ADMIN_PASSWORD.")\"");
	http_response_code(401);

	echo <<< EOB
		<!DOCTYPE html>
		<html>
		<body>
		<center>
		<h1>Rejected!</h1>
		<hr>
		<p>Wrong Username or Password!</p>
		</center>
		</body>
		</html>
		EOB;
	exit;
}
?>

