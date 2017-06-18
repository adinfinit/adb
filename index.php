<?php

if($_SERVER['REQUEST_METHOD'] != "POST"){
	http_response_code(405);
	die("invalid method");
}

$input = file_get_contents('php://input');
$requests = json_decode($input);

if(!isset($requests)){
	http_response_code(400);
	die("invalid request body");
}

$path = trim($_SERVER['REQUEST_URI'], '/');
$tokens = explode("/", $path, 2);

if(count($tokens) < 2){
	http_response_code(404);
	die("invalid path");
}

$backend = $tokens[0];
$name = $tokens[1];

if($backend != "store"){
	http_response_code(400);
	die("invalid backend " . $backend . " for " . $name);
}

require_once "adb.php";
require_once "../db.config.php";

try {
	$store = new adb\PDOStore($db, $name);
	$store->begin();

	$results = array();
	foreach($requests as $request){
		$entry = new adb\Entry();
		$entry->from_object($request->data);

		switch($request->type){
		case "insert":
			if(!$entry->is_full()){
				
				header('Content-Type: application/json');
				http_response_code(400);
				$results[] = array(
					"error" => "invalid entry",
					"details" => $entry->debug()
				);

				echo json_encode($results);
				
				$store->rollback();
				die();
			}

			$store->insert($entry);

			break;
		case "select":
			if(!$entry->is_partial()){
				header('Content-Type: application/json');
				http_response_code(400);
				$results[] = array(
					"error" => "invalid entry",
					"details" => $entry->debug()
				);

				echo json_encode($results);

				$store->rollback();
				die();
			}

			break;
		case "delete":
			if(!$entry->is_partial()){
				header('Content-Type: application/json');
				http_response_code(400);
				$results[] = array(
					"error" => "invalid entry",
					"details" => $entry->debug()
				);

				echo json_encode($results);

				$store->rollback();
				die();
			}



			break;
		}
	}

	$store->commit();
} catch(Exception $e) {
	$store->rollback();
	http_response_code(500);
	die("exception " . $e->getMessage());
}

?>