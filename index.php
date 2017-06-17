<?php
// https://www.leaseweb.com/labs/2015/10/creating-a-simple-rest-api-in-php/
require "adb.php";

$input = file_get_contents('php://input');
$events = adb\Event::from_json_multiple($input);

if(!isset($events)){
	http_response_code(400);
	die("invalid json data");
}

$errors = array();
foreach($events as $event){
	if(!$event->is_full()){
		$errors[] = $event->debug();
	}
}

header('Content-Type: application/json');

if(count($errors) > 0){
	http_response_code(400);
	echo(json_encode($errors));
	die();
}

echo(json_encode($events));

die();

$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_SERVER['PATH_INFO'], '/');
$request = explode('/', $path);

header('Content-Type: application/json');
switch($_SERVER['REQUEST_METHOD']){
case 'GET':
	
	break;
case 'PUT':

	break;
}


?>