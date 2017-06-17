<?php
namespace adb;

function is_iso8601($value): bool {
	if(!is_string($value)){
		return false;
	}
	if(preg_match('/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/', $value)){
		return true;
	}
	return false;
}

class Entry {
	var $id;
	var $version;
	var $date;
	var $meta;
	var $type;
	var $data;

	public function assign_object($data){
		function prop($data, $name){
			if(isset($data->$name)){
				return $data->$name;
			}
		}

		$this->id = prop($data, "id");
		$this->version = prop($data, "version");
		$this->date = prop($data, "date");
		$this->meta = prop($data, "meta");
		$this->type = prop($data, "type");
		$this->data = prop($data, "data");
	}

	public function is_full(): bool {
		return isset($this->id, $this->version, $this->date, $this->meta, $this->type, $this->data) && $this->is_partial();
	}

	public function is_partial(): bool {
		return true &&
			(!isset($this->id) || is_string($this->id)) &&
			(!isset($this->version) || is_int($this->version)) &&
			(!isset($this->date) || is_iso8601($this->date)) &&
			(!isset($this->meta) || is_string($this->meta)) &&
			(!isset($this->type) || is_string($this->type)) &&
			(!isset($this->data) || is_string($this->data));
	}

	public function debug(): array {
		$info = array();
		function debug_info($prop, $value, $valid){
			if(!isset($value)){
				return array("error"=>"missing");
			}
			if(!$valid){
				return array("value"=>$value, "error"=>"invalid");
			}
			return array("value"=>$value);
		}
		$info["id"] = debug_info("id", $this->id, is_string($this->id));
		$info["version"] = debug_info("version", $this->version, is_int($this->version));
		$info["date"] = debug_info("date", $this->date, is_iso8601($this->date));
		$info["meta"] = debug_info("meta", $this->meta, is_string($this->meta));
		$info["type"] = debug_info("type", $this->type, is_string($this->type));
		$info["data"] = debug_info("data", $this->data, is_string($this->data));
		return $info;
	}

	public static function from_json_multiple($json): array {
		$events = array();
		$items = json_decode($json);
		if(!isset($items)){
			return;
		}
		foreach ($items as $item) {
			$events[] = Entry::from_object($item);
		}
		return $events;
	}
	public static function from_json($json): Entry {
		$arr = json_decode($json);
		if($arr){
			return Entry::from_object($arr);
		}
	}
	public static function from_object($array): Entry {
		$ev = new Entry();
		$ev->assign_object($array);
		return $ev;
	}
}

?>