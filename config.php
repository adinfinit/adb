<?php

$DB_HOST = "localhost";
$DB_NAME = "adb";
$DB_USER = "root";
$DB_PASS = "";

function trusted_user($user, $pass) {
	if($user == "user" && $pass == "pass"){
		return true;
	}
	return false;
}

?>