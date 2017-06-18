<?php
namespace adb;

require_once "adb.entry.php";
use Exception;

class InvalidEntry extends Exception {};
class DatabaseException extends Exception {};

interface Store {
	public function ensure();
    public function destroy();

    public function upsert(Entry $entry);
    public function select(Entry $entry);
}

class PDOStore implements Store {
    var $pdo;
    var $name;
    var $table;

    function __construct($pdo, $name){
        $this->pdo = $pdo;
        $this->name = $name;
        $this->table = "blob_" . $name;
    }

    public function ensure() : bool{
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `$this->table` (
                `id` BINARY(16) NOT NULL,
                `version` INT NOT NULL DEFAULT 0,
                `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `meta` TEXT NOT NULL,
                `type` TEXT NOT NULL,
                `data` TEXT NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `id_UNIQUE` (`id` ASC)
            );");
    }

    public function destroy() : bool {
        return $this->pdo->exec("DROP TABLE `$this->table`;");
    }

    public function upsert(Entry $entry) {
    	if(!$entry->is_full()){
    		throw new InvalidEntry("Entry is invalid: " . $entry->info());
    	}

    	$sql = "
    		REPLACE INTO `$this->table` 
    			(`id`, `version`, `date`, `meta`, `type`, `data`)
    		VALUES
    			(:id, :version, :date, :meta, :type, :data)
    	;";

    	$stmt = $this->pdo->prepare($sql);
    	if(!$stmt->execute($entry->binding())){
    		throw new DatabaseException("REPLACE INTO failed ($stmt->errno): $stmt->error");
    	}
    }

    public function select(Entry $template): array {
        $sql = "SELECT (`id`, `version`, `date`, `meta`, `type`, `data`)\n\tFROM `$this->table`\n";

        $binding = $template->binding();

        if(count($binding) != 0){
        	$sql .= "WHERE\n";
	        foreach($binding as $bindname){
	        	$sql .= "\t$bindname = :$bindname\n";
	        }
	    }

        $stmt = $this->pdo->prepare($sql);
        if(!$stmt->execute($binding)){
        	throw new DatabaseException("SELECT failed ($stmt->errno): $stmt->error");
        }

        return array();
    }
}

/*
class Store {
	var $backend;
	var $name;

	public function __construct($db, $name) {
	    $this->backend = new MysqlStore($db, "blob_" . $name);
		$this->name = $name;

		$this->backend->ensureTable();
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
	    global $mysql56_get_by_id;

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
		return null;
	}

	public function upsert(Entry $entry): int {
        return 0;
	}
}
*/
?>