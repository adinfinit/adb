<?php

for ($i = 0; $i < 10; $i++) echo "\r\n";

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
	"type": "note",
	"data": {"title": "something"}
}');

$b2 = adb\Entry::from_json('{
	"id": "BBBBBBBB-0000-0000-0000-000000000000",
	"version": 20,
	"date": "2018-06-18T13:35:27Z",
	"meta": {"source": "valid"},
	"type": "note",
	"data": {"title": "something"}
}');

$c = adb\Entry::from_json('{
	"id": "CCCCCCCC-0000-0000-0000-000000000000",
	"version": 20,
	"date": "2017-06-18T13:35:27Z",
	"meta": {"source": "todo"},
	"type": "note",
	"data": {"title": "something"}
}');

$store->upsert($a);
$store->upsert($b1);
$store->upsert($b2);
$store->upsert($c);

function test_select($store, $template){
	echo "= SELECT = " . $template . "\n";
	$entries = $store->select(adb\Entry::from_json($template));
	foreach($entries as $index => $entry){
		echo " $index :" . json_encode($entry) . "\n";
	}
}

test_select($store, '{}');
test_select($store, '{"id": "AAAAAAAA-0000-0000-0000-000000000000"}');
test_select($store, '{"id": "BBBBBBBB-0000-0000-0000-000000000000"}');
test_select($store, '{"type": "note"}');
test_select($store, '{"version": 20}');

?>