<?php
namespace adb;

require_once "entry.php";

class Stream {
	var $stream;
	var $database;
	var $tx;

	public function __construct($stream, $database){
		$this->stream = $stream;
		$this->database = $database;
	}

	function begin(){

	}

	function commit(){

	}

	function rollback(){

	}

	function append($event){

	}
}

?>