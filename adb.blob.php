<?php
namespace adb;

require_once "adb.entry.php";
require_once "adb.pdo.php";

use Exception;
use PDO;

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

class PDOStore extends PDOTable implements Store{

	function __construct($pdo, $name){
		parent::__construct($pdo, $name, "blob_" . $name);
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
}

?>