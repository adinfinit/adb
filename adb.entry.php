<?php
namespace adb;

// is_iso8601 returns whether value is a valid ISO8601 date-time
function is_iso8601($value): bool {
	if(!is_string($value)){
		return false;
	}
	if(preg_match('/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/', $value)){
		return true;
	}
	return false;
}

function is_json($value): bool {
	if(!is_object($value)){
		return false;
	}

	$data = json_encode($value);
	return isset($data);
}

function is_uuid($value): bool {
	if(!is_string($value)){
		return false;
	}
	if(preg_match("/^(\{)?[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}(?(1)\})$/i", $value)){
		return true;
	}
	return false;
}

function uuid_to_binary(string $uuid) {
	return pack("h*", str_replace('-', '', $uuid));
}

function binary_to_uuid($binary): string {
	$value = unpack("h*", $binary);
	$value = preg_replace("/([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})/", "$1-$2-$3-$4-$5", $value);
	return $value[1];
}

function get_ifset($data, string $prop) {
	if(is_array($data)){
		if(isset($data[$prop])){
			return $data[$prop];
		}
	} else {
		if(isset($data->$prop)){
			return $data->$prop;
		}
	}
}

function check_as_detailed(array &$info, string $prop, $value, bool $valid){
	if(!isset($value)){
		$info[$prop] = array("error"=>"missing");
	} else if(!$valid){
		$info[$prop] = array("value"=>$value, "error"=>"invalid");
	} else {
		$info[$prop] = array("value"=>$value);
	}
}

function check_as_string(array &$info, string $prop, $value, bool $valid) {
	if(!isset($value)){
		$info[] = "missing " . $prop;
	} else if(!$valid){
		$info[] = "invalid " . $prop;
	}
}

// Entry is a general value that can be stored in a stream or blobstore
class Entry {
	var $id;
	var $version;
	var $date;
	var $meta;
	var $type;
	var $data;

	// assign_object copies values from data
	public function assign_object($data){
		$this->id = get_ifset($data, "id");
		$this->version = get_ifset($data, "version");
		$this->date = get_ifset($data, "date");
		$this->meta = get_ifset($data, "meta");
		$this->type = get_ifset($data, "type");
		$this->data = get_ifset($data, "data");
	}

	// is_full returns whether all fields have been correctly filled
	public function is_full(): bool {
		return isset($this->id, $this->version, $this->date, $this->meta, $this->type, $this->data) && $this->is_partial();
	}

	// is_partial returns whether filled fields are correct
	public function is_partial(): bool {
		return true &&
			(!isset($this->id) || is_uuid($this->id)) &&
			(!isset($this->version) || is_int($this->version)) &&
			(!isset($this->date) || is_iso8601($this->date)) &&
			(!isset($this->meta) || is_json($this->meta)) &&
			(!isset($this->type) || is_string($this->type)) &&
			(!isset($this->data) || is_json($this->data));
	}

	public function binding(): array {
		$bind = array();
		isset($this->id) && ($bind["id"] = uuid_to_binary($this->id));
		isset($this->version) && ($bind["version"] = $this->version);
		isset($this->date) && ($bind["date"] = $this->date);
		isset($this->meta) && ($bind["meta"] = json_encode($this->meta));
		isset($this->type) && ($bind["type"] = $this->type);
		isset($this->data) && ($bind["data"] = json_encode($this->data));
		return $bind;
	}

	public function unbind($data) {
		$this->id = binary_to_uuid($data["id"]);
		$this->version = $data["version"];
		$this->date = $data["date"];
		$this->meta = json_decode($data["meta"], false);
		$this->type = $data["type"];
		$this->data = json_decode($data["data"], false);
	}

	// debug returns an array of validation errors
	public function debug(): array {
		$info = array();
		check_as_detailed($info, "id", $this->id, is_uuid($this->id));
		check_as_detailed($info, "version", $this->version, is_int($this->version));
		check_as_detailed($info, "date", $this->date, is_iso8601($this->date));
		check_as_detailed($info, "meta", $this->meta, is_json($this->meta));
		check_as_detailed($info, "type", $this->type, is_string($this->type));
		check_as_detailed($info, "data", $this->data, is_json($this->data));
		return $info;
	}

	// debug returns an array of validation errors
	public function info(): string {
		$info = array();
		check_as_string($info, "id", $this->id, is_uuid($this->id));
		check_as_string($info, "version", $this->version, is_int($this->version));
		check_as_string($info, "date", $this->date, is_iso8601($this->date));
		check_as_string($info, "meta", $this->meta, is_json($this->meta));
		check_as_string($info, "type", $this->type, is_string($this->type));
		check_as_string($info, "data", $this->data, is_json($this->data));	
		return join(", ", $info);
	}

	// from_json_multiple returns an array of entries based on json string
	public static function from_json_multiple(string $json): array {
		$events = array();
		$items = json_decode($json, false);
		if(!isset($items)){
			return null;
		}
		foreach ($items as $item) {
			$events[] = Entry::from_object($item);
		}
		return $events;
	}

	// from_json returns single entry from json string
	public static function from_json(string $json): Entry {
		$arr = json_decode($json, false);
		if($arr){
			return Entry::from_object($arr);
		}
	}

	// from_object returns single entry based on object data
	public static function from_object($data): Entry {
		$ev = new Entry();
		$ev->assign_object($data);
		return $ev;
	}
}

?>