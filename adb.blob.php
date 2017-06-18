<?php
namespace adb;

require_once "adb.entry.php";
use Exception;
use PDO;

class InvalidEntry extends Exception {};
class DatabaseException extends Exception {};

interface Store {
	public function ensure();
	public function destroy();

	public function upsert(Entry $entry);
	public function select(Entry $template);
	public function delete(Entry $template);

	public function begin();
	public function rollback();
	public function commit();
}

function formatError($pdo){
	return ": " . join("; ", $pdo->errorInfo());
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

	public function ensure() {
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

	public function destroy() {
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
			$error = formatError($stmt);
			throw new DatabaseException("REPLACE INTO failed $error");
		}
	}

	function where($binding) : string {
		$sql = "";
		if(count($binding) != 0){
			$sql .= "  WHERE ";
			$first = true;
			foreach($binding as $bindname => $bindvalue){
				if($first) {
					$sql .= "$bindname = :$bindname\n";
					$first = false;
				} else {
					$sql .= "   AND $bindname = :$bindname\n";
				}
			}
		}
		return $sql;
	}

	public function select(Entry $template): array {
		$binding = $template->binding();

		$sql = "SELECT `id`, `version`, `date`, `meta`, `type`, `data`\n  FROM `$this->table`\n" . $this->where($binding);
	  
		$stmt = $this->pdo->prepare($sql);
		if(!$stmt->execute($binding)){
			$error = formatError($stmt);
			throw new DatabaseException("SELECT failed $error");
		}

		$entries = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			$entry = new Entry();
			$entry->unbind($row);
			$entries[] = $entry;
		}

		$stmt->closeCursor();

		return $entries;
	}

	public function delete(Entry $template) {
		$binding = $template->binding();

		$sql = "DELETE FROM `$this->table`\n" . $this->where($binding);
	  
		$stmt = $this->pdo->prepare($sql);
		if(!$stmt->execute($binding)){
			$error = formatError($stmt);
			throw new DatabaseException("DELETE failed $error");
		}
	}

	public function deleteOne(Entry $template) {
		$this->begin();

		$binding = $template->binding();

		$sql = "DELETE FROM `$this->table`\n" . $this->where($binding);
	  
		$stmt = $this->pdo->prepare($sql);
		if(!$stmt->execute($binding)){
			$error = formatError($stmt);
			$this->rollback();
			throw new DatabaseException("DELETE failed $error");
		}

		if($this->rowCount() > 1){
			$this->rollback();
			throw new DatabaseException("DELETE failed, too many rows");
		}

		$this->commit();
	}

	// nested transaction support
	var $transaction = 0;    
	public function begin(){
		$this->transaction++;
		if($this->transaction == 1){
			if(!$this->pdo->beginTransaction()){
				$error = formatError($this->pdo);
				throw new DatabaseException("TX begin $error");
			}
			return;
		}

		if(!$this->pdo->exec('SAVEPOINT trans'.$this->transaction)){
			$error = formatError($this->pdo);
			throw new DatabaseException("TX begin nest $error");
		}
	}
	public function rollback(){
		$this->transaction--;
		if ($this->transaction > 0) {
			if(!$this->pdo->exec('ROLLBACK TO trans'.$this->transaction + 1)){
				$error = formatError($this->pdo);
				throw new DatabaseException("TX rollback nest $error");
			}
			return;
		}

		if(!$this->pdo->rollBack()){
			$error = formatError($this->pdo);
			throw new DatabaseException("TX rollback $error");
		}

		if($this->transaction < 0){
			throw new DatabaseException("TX rollback negative");
		}
	}
	public function commit(){
		$this->transaction--;
		if ($this->transaction > 0) {
			if(!$this->pdo->exec('RELEASE trans' . $this->transaction + 1)){
				$error = formatError($this->pdo);
				throw new DatabaseException("TX begin nest $error");
			}
			
			return;
		}

		if(!$this->pdo->commit()){
			$error = formatError($this->pdo);
			throw new DatabaseException("TX commit $error");
		}

		if($this->transaction < 0){
			throw new DatabaseException("TX commit negative");
		}
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