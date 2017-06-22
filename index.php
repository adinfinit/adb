<?php

require_once "config.php";

if(!isset($_SERVER['PHP_AUTH_USER'])){
	http_response_code(401);
	die("unauthorized");
}

$user = $_SERVER['PHP_AUTH_USER'];
$pass = $_SERVER['PHP_AUTH_PW'];
if(!trusted_user($user, $pass)){
	http_response_code(401);
	die("unauthorized `$user`");
}

if($_SERVER['REQUEST_METHOD'] != "POST"){
	http_response_code(405);
	die("invalid method");
}

$input = file_get_contents('php://input');
$body = json_decode($input);

if(!isset($body) || !isset($body->method) || !isset($body->data)){
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

require_once "adb/adb.php";

$db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS);

class RequestFailed extends Exception {
	var $path;
	var $title;
	var $details;

	public function __construct($path, $title, $details){
		$this->path = $path;
		$this->title = $title;
		$this->details = $details;
	}
}

function process($store, $path, $request) {
	try {
		switch($request->method){
		case "transaction":
			$store->begin();
			try {
				$results = array();
				foreach($request->data as $index => $request) {
					$results[] = process($store, $path . "data/$index/", $request);
				}
				$store->commit();
				return array(
					"method" => "transaction",
					"data" => $results
				);
			} catch (Exception $e) {
				$store->rollback();
				throw $e;
			}

			break;
		case "insert":
			$entry = adb\Entry::from_object($request->data);
			if(!$entry->is_full()){
				throw new RequestFailed($path, "invalid entry", $entry->debug());
			}

			return array(
				"method" => "insert",
				"data" => $store->upsert($entry)
			);
		case "select":
			$entry = adb\Entry::from_object($request->data);
			if(!$entry->is_partial()){
				throw new RequestFailed($path, "invalid entry", $entry->debug());
			}

			return array(
				"method" => "select",
				"data" => $store->select($entry)
			);
		case "delete":
			$entry = adb\Entry::from_object($request->data);
			if(!$entry->is_partial()){
				throw new RequestFailed($path, "invalid entry", $entry->debug());
			}

			return array(
				"method" => "delete",
				"data" => $store->delete($entry)
			);
		default:
			throw new Exception("unknown method " . $request->method);
		}
	} catch (RequestFailed $e){
		throw $e;
	} catch (Exception $e) {
		throw new RequestFailed($path, $e->getMessage(), $request);
	}
}

try {
	$store = new adb\PDOStore($db, $name);
	$store->ensure();
	$response = process($store, "/", $body);
	
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode($response);
	die();
	
} catch (RequestFailed $e) {
	http_response_code(400);
	header('Content-Type: application/json');
	$response = array(
		"errors" => array(
			array(
				"title" => $e->title,
				"source" => array("pointer" => $e->path),
				"meta" => $e->details
			)
		)
	);
	echo json_encode($response);
	die();
} catch (Exception $e) {
	http_response_code(500);
	header('Content-Type: application/json');
	$response = array(
		"errors" => array(
			array("title" => $e->getMessage())
		)
	);
	echo json_encode($response);
	die();
}

?>