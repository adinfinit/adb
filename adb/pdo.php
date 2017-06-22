<?php
namespace adb;

require_once "entry.php";

use Exception;
use PDO;

class InvalidEntry extends Exception {};
class DatabaseException extends Exception {};

class PDOTable {
	var $pdo;
	var $name;
	var $table;

	function __construct($pdo, $name, $table){
		$this->pdo = $pdo;
		$this->name = $name;
		$this->table = $table;
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

	protected function where($binding) : string {
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

?>