<?php

namespace legionpe;

use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\MainLogger;
use pocketmine\utils\TextFormat;

class MysqlConnection{
	const RAW = 0;
	const ASSOC = 1;
	const ALL = 2;
	/** @var \mysqli */
	private $mysqli;
	private $nextChampionship;
	public function __construct(LegionPE $main){
		// hiding away sensitive data



































		$mysqli = new \mysqli("127.0.0.1", "root", "", "legionpe", 3306);


































		// hiding away sensitive data
		if($mysqli->connect_error){
			die("Cannot connect to MySQL server: {$mysqli->connect_error}");
		}
		$this->mysqli = $mysqli;
		$this->nextChampionship(true);
	}
	public function close(){
		$this->mysqli->close();
	}
	public function query($msg, $fetch, ...$args){
		foreach($args as &$arg){
			if(is_string($arg)){
				$arg = "'" . $this->mysqli->escape_string($arg) . "'";
			}
			else{
				$arg = "$arg";
			}
		}
		if(count($args) > 0){
			try{
				if($fetch !== 3){
					$result = $this->mysqli->query($query = sprintf($msg, ...$args));
				}else{
					return sprintf($msg, ...$args);
				}
			}
			catch(\RuntimeException $e){
				MainLogger::getLogger()->alert($e->getTraceAsString());
				MainLogger::getLogger()->alert("Query: " . TextFormat::YELLOW . $msg);
				MainLogger::getLogger()->alert("Args: " . var_export($args, true));
				return null;
			}
		}
		else{
			$result = $this->mysqli->query($query = $msg); // to make it faster
		}
		if($result === false){
			$server = Server::getInstance();
			$server->getLogger()->warning("Query failed! Query:");
			$server->getLogger()->warning($query);
			$server->getLogger()->warning("Message: " . $this->mysqli->error);
		}
		if($result instanceof \mysqli_result){
			if($fetch === self::ASSOC){
				return $result->fetch_assoc();
			}
			elseif($fetch === self::ALL){
				return $result->fetch_all(MYSQLI_ASSOC);
			}
			return $result;
		}
		return $result;
	}
	public function nextUID(){
		$this->mysqli->query("LOCK TABLES ids WRITE");
		$result = $this->mysqli->query("SELECT id FROM ids WHERE name='uid';");
		$id = $result->fetch_assoc()["id"];
		$result->close();
		$this->mysqli->query("UPDATE ids SET id=id+1 WHERE name='uid';");
		$this->mysqli->query("UNLOCK TABLES");
		return (int) $id;
	}
	public function nextTID(){
		$this->mysqli->query("LOCK TABLES ids WRITE");
		$result = $this->mysqli->query("SELECT id FROM ids WHERE name='tid';");
		$id = $result->fetch_assoc()["id"];
		$result->close();
		$this->mysqli->query("UPDATE ids SET id=id+1 WHERE name='tid';");
		$this->mysqli->query("UNLOCK TABLES");
		return (int) $id;
	}
	public function fastSearchPlayerUID($name){
		if($name instanceof Session){
			$name = $name->getPlayer();
		}
		if($name instanceof Player){
			$name = $name->getName();
		}
		$name = strtolower($name);
		$result = $this->query("SELECT uid FROM players WHERE primaryname=%s;", self::ASSOC, $name);
		if(is_array($result)){
			return (int) $result["uid"];
		}
		return null;
	}
	/**
	 * Warning: this function doesn't save members!
	 * @param Team[] $teams
	 * @return bool
	 */
	public function saveTeams(array $teams){
		$query = 'INSERT INTO teams(tid,name,open,invites,requires,rules)VALUES';
		$values = [];
		foreach($teams as $team){
			$values[] = "(" . implode(",", array_map(
					array($this, "escStr"),
						[
							$team->tid, $team->name, $team->open,
							implode(",", array_map(function($uid){
								return "$uid";
							}, array_keys($team->invited))),
							$team->requires, $team->rules
						]
					)) . ")";
		}
		$query .= implode(",", $values);
		$query .= "ON DUPLICATE KEY UPDATE name=VALUES(name),open=VALUES(open),invites=VALUES(invites),requires=VALUES(requires),rules=VALUES(rules)";
		return $this->query($query, self::RAW);
	}
	public function saveTeam(Team $team){
		return $this->saveTeams([$team]);
	}
	public function nextChampionship($force = false){
		if($force){
			return ($this->nextChampionship = $this->query("SELECT id FROM ids WHERE name='nextcham'", self::ASSOC)["id"]);
		}
		return $this->nextChampionship;
	}
	public function incrementNextChampionship(){
		$this->nextChampionship += 604800;
		$this->query("UPDATE ids SET id=%d WHERE name='nextchan'", self::RAW, $this->nextChampionship);
	}
	public function measurePing(&$micro){
		$micro = -microtime(true);
		$result = $this->mysqli->ping();
		$micro += microtime(true);
		return $result;
	}
	public function escStr($str){
		if(is_bool($str)){
			return $str ? "1":"0";
		}
		return is_string($str) ? "'{$this->mysqli->escape_string($str)}'" : "$str";
	}
}
