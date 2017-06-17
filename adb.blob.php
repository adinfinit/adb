<?php
namespace adb;
require_once "adb.entry.php";

$mysql56_create = "
	CREATE TABLE IF NOT EXISTS :name (
		`id` BINARY(16) NOT NULL DEFAULT unhex(replace(UUID(), '-', '')),
		`version` INT NOT NULL DEFAULT 0,
		`date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`meta` TEXT NOT NULL,
		`type` TEXT NOT NULL,
		`data` TEXT NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE INDEX `id_UNIQUE` (`id` ASC)
	);";

$mysql56_select = "
	SELECT (`id`, `version`, `date`, `meta`, `type`, `data`)
	FROM :name
";

$mysql56_get_all = $mysql56_select . ";";
$mysql56_get_by_id = $mysql56_select . "WHERE `id` = :id;";

class Store {
	var $db;
	var $name;

	public function __construct($db, $name) {
		$this->db = $db;
		$this->name = $name;
		$this->createTable();
	}

	function tablename() { return "blob_" . $this->name; }

	function createTable(){
		$stmt = $this->db->prepare($mysql56_create);
		$stmt->execute(array(":name" => $this->tablename()));
	}

	public function beginTransaction() {
		return $this->db->beginTransaction();
	}

	public function commit() {
		return $this->db->commit();
	}

	public function rollback() {
		return $this->db->rollBack();
	}

	public function byId(string $id): Entry {
		$stmt = $this->db->prepare($mysql56_get_by_id);
		$ok = $stmt->execute(array(
			":name" => $this->tablename(),
			":id" => uuid_to_binary($id)
		));

		if($ok){
			$row = $stmt->fetch();

			$entry = new Entry();
			$entry->id = binary_to_uuid($row->id);
			$entry->version = $row->version;
			$entry->date = $row->date;
			$entry->meta = $row->meta;
			$entry->type = $row->type;
			$entry->data = $row->data;

			$stmt->closeCursor();

			return $entry;
		}
	}

	public function all(): array {
		$stmt 
	}

	public function upsert(Entry $entry): int {

	}
}

?>