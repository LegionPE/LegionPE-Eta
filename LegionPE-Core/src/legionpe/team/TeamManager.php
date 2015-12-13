<?php

namespace legionpe\team;

use legionpe\config\Settings;
use legionpe\LegionPE;
use legionpe\MysqlConnection;
use pocketmine\scheduler\PluginTask;

class TeamManager extends PluginTask{
	/** @var LegionPE */
	private $main;
	/** @var Team[] */
	private $teams = [];
	private $sql;
	public function __construct(LegionPE $main){
		parent::__construct($main);
		$this->main = $main;
		$main->getServer()->getScheduler()->scheduleDelayedRepeatingTask($this, 2400, 2400);
		$this->sql = new \SQLite3(":memory:");
		$this->sql->exec("CREATE TABLE teams (id INTEGER PRIMARY KEY, name TEXT COLLATE NOCASE, open INTEGER)");
		$this->main->getMySQLi()->query("UPDATE teams SET has_founder=0 WHERE isnull((SELECT uid FROM players WHERE tid=teams.tid AND teamrank=%d))", MysqlConnection::RAW, Team::RANK_LEADER);
		$this->main->getMySQLi()->query("UPDATE players SET tid=-1,teamrank=0 WHERE(SELECT has_founder FROM teams WHERE tid=players.tid)=0", MysqlConnection::RAW);
		$this->main->getMySQLi()->query("DELETE FROM teams WHERE(SELECT COUNT(*)FROM players WHERE players.tid=teams.tid)=0", MysqlConnection::RAW);
		foreach($this->main->getMySQLi()->query("SELECT *,(SELECT rank FROM players WHERE tid=teams.tid AND teamrank=4 LIMIT 1)AS leaderrank FROM teams", MysqlConnection::ALL) as $row){
			$tid = (int) $row["tid"];
			$members = [];
			foreach($this->main->getMySQLi()->query("SELECT uid,teamrank FROM players WHERE tid=$tid", MysqlConnection::ALL) as $member){
				$members[(int) $member["uid"]] = (int) $member["teamrank"];
			}
			$this->teams[$tid] = new Team(
				$main, $tid, $row["name"], Settings::team_maxCapacity($row["leaderrank"]), (bool) ((int) $row["open"]), $members, isset($row["invited"]) ? $row["invited"]:"", isset($row["requires"]) ? $row["requires"]:"", isset($row["rules"]) ? $row["rules"]:"");
			$op = $this->sql->prepare("INSERT INTO teams (id, name, open)VALUES(:id, :name, :open)");
			$op->bindValue(":id", $tid);
			$op->bindValue(":name", $row["name"]);
			$op->bindValue(":open", $row["open"]);
			$op->execute();
		}
	}
	public function addTeam(Team $team){
		$this->teams[$team->tid] = $team;
		$op = $this->sql->prepare("INSERT INTO teams (id, name, open) VALUES (:id, :name, :open)");
		$op->bindValue(":id", $team->tid);
		$op->bindValue(":name", $team->name);
		$op->bindValue(":open", $team->open);
		$op->execute();
	}
	public function rmTeam(Team $team){
		$this->sql->exec("DELETE FROM teams WHERE id = $team->tid");
		unset($this->teams[$team->tid]);
	}
	public function getTeam($id){
		return isset($this->teams[$id]) ? $this->teams[$id]:null;
	}
	public function getTeams(){
		return $this->teams;
	}
	public function getTeamByName($name){
		$op = $this->sql->prepare("SELECT id,name FROM teams WHERE name LIKE :name ORDER BY LENGTH(name) LIMIT 1");
		$op->bindValue(":name", $name . "%");
		$result = $op->execute();
		$array = $result->fetchArray(SQLITE3_ASSOC);
		$result->finalize();
		if(!is_array($array)){
			return null;
		}
		return $this->getTeam($array["id"]);
	}
	public function getTeamByExactName($name){
		$op = $this->sql->prepare("SELECT id FROM teams WHERE name = :name");
		$op->bindValue(":name", $name);
		$result = $op->execute();
		$row = $result->fetchArray(SQLITE3_ASSOC);
		$result->finalize();
		if(!is_array($row)){
			return null;
		}
		return $this->getTeam($row["id"]);
	}
	public function saveTeams(){
		$this->getMain()->getMySQLi()->saveTeams($this->teams);
	}
	public function onRun($t){
		$this->saveTeams();
	}
	public function saveTeam(Team $team){
		$this->getMain()->getMySQLi()->saveTeam($team);
	}
	/**
	 * @param Team $team
	 * @return \legionpe\session\Session[]
	 */
	public function getSessionsOfTeam(Team $team){
		$sessions = [];
		foreach($this->getMain()->getSessions()->getAll() as $ses){
			if($ses->getTeam() === $team){
				$sessions[$ses->getUID()] = $ses;
			}
		}
		return $sessions;
	}
	/**
	 * @return LegionPE
	 */
	public function getMain(){
		return $this->main;
	}
	public function __destruct(){
		$this->sql->close();
	}
	/**
	 * @return \SQLite3
	 */
	public function getSQLite3(){
		return $this->sql;
	}
}
