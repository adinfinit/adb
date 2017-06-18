<?php
require_once "adb.php";

$db = new PDO('mysql:host=localhost;dbname=adb_test', "root", "");

$store = new adb\PDOStore($db, "todo");
$store->destroy();
$store->ensure();

$a = adb\Entry::from_json('{
	"id": "AAAAAAAA-0000-0000-0000-000000000000",
	"version": 20,
	"date": "2017-06-18T13:35:27Z",
	"meta": {"source": "todo"},
	"type": "xyz",
	"data": {"title": "something"}
}');

$b1 = adb\Entry::from_json('{
	"id": "BBBBBBBB-0000-0000-0000-000000000000",
	"version": 1,
	"date": "2017-06-18T13:35:27Z",
	"meta": {"source": "invalid"},
	"type": "xyz",
	"data": {"title": "something"}
}');

$b2 = adb\Entry::from_json('{
	"id": "BBBBBBBB-0000-0000-0000-000000000000",
	"version": 20,
	"date": "2018-06-18T13:35:27Z",
	"meta": {"source": "valid"},
	"type": "xyz",
	"data": {"title": "something"}
}');

$c = adb\Entry::from_json('{
	"id": "CCCCCCCC-0000-0000-0000-000000000000",
	"version": 20,
	"date": "2017-06-18T13:35:27Z",
	"meta": {"source": "todo"},
	"type": "xyz",
	"data": {"title": "something"}
}');

$store->upsert($a);
$store->upsert($b1);
$store->upsert($b2);
$store->upsert($c);

?>