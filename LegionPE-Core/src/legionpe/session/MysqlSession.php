<?php

namespace legionpe\session;

use legionpe\LegionPE;
use legionpe\MysqlConnection;

class MysqlSession{
	private $session;
	public $data;
	public $lastCoins;
	public $onSince;
	public function __construct(Session $session){
		$this->session = $session;
		$this->onSince = time();
//		$this->data = $this->session->getMain()->getMySQLi()->query(
//			"SELECT * FROM players WHERE INSTR(names, %s);",
//			MysqlConnection::ASSOC, $session->getPlayer()->getName() . "|");
		$isOld = is_array($this->data = $this->session->getMain()->getMySQLi()->query("SELECT * FROM players WHERE primaryname=%s;", MysqlConnection::ASSOC, strtolower($session->getPlayer()->getName())));
		if(!$isOld){
			$session->getMain()->getStats()->increment(LegionPE::TITLE_LEGIONPE_NEW_JOINS);
		}
		else{
			foreach(["uid", "lastonline", "registry", "ipconfig", "notag", "lastgrind", "rank", "tid", "teamrank", "teamjointime", "warnpts"] as $key){
				if(!isset($this->data[$key])){
					$this->data[$key] = 0;
				}
				else{
					$this->data[$key] = (int) $this->data[$key];
				}
			}
			$this->data["coins"] = $this->lastCoins = (float) $this->data["coins"];
		}
	}
	public function getRank(){
		return is_array($this->data) ? $this->data["rank"] : 0;
	}
	public function &getData(){
		return $this->data;
	}
	public function setData($data = null){
		if($data !== null){
			$this->data = $data;
		}
		$this->data["lastonline"] = time();
		self::saveData([$this], $this->session->getMain()->getMySQLi());
	}
	/**
	 * @param self[] $sessions
	 * @param MysqlConnection $conn
	 */
	public static function saveData($sessions, MysqlConnection $conn){
		$query = "INSERT INTO players(uid,names,hash,coins,lastonline,registry,lastip,histip,ipconfig,ignoring,primaryname,rank,notag,tid,teamrank,teamjointime,lastgrind,warnpts,tmpdelta,ontime,timesince)VALUES";
		$query .= implode(",", array_fill(0, count($sessions), "(%d,%s,%s,%g,%d,%d,%s,%s,%d,%s,%s,%d,%d,%d,%d,%d,%d,%d,%g,%d,%d)"));
		$query .= "ON DUPLICATE KEY UPDATE names=VALUES(names),coins=coins+VALUES(tmpdelta),lastonline=VALUES(lastonline),lastip=VALUES(lastip),histip=VALUES(histip),ipconfig=VALUES(ipconfig),ignoring=VALUES(ignoring),notag=VALUES(notag),tid=VALUES(tid),teamrank=VALUES(teamrank),teamjointime=VALUES(teamjointime),lastgrind=VALUES(lastgrind),warnpts=VALUES(warnpts),ontime=ontime+VALUES(ontime)";
		$args = [];
		foreach($sessions as $ses){
			$data = $ses->data;
			$args[] = (int) $data["uid"];
			$args[] = $data["names"];
			$args[] = $data["hash"];
			$args[] = $data["coins"];
			$args[] = $data["lastonline"];
			$args[] = $data["registry"];
			$args[] = $data["lastip"];
			$args[] = $data["histip"];
			$args[] = $data["ipconfig"];
			$args[] = $data["ignoring"];
			$args[] = $data["primaryname"];
			$args[] = $data["rank"];
			$args[] = $data["notag"];
			$args[] = $data["tid"];
			$args[] = $data["teamrank"];
			$args[] = $data["teamjointime"];
			$args[] = $data["lastgrind"];
			$args[] = $data["warnpts"];
			$args[] = $data["coins"] - $ses->lastCoins;
			$ses->lastCoins = $data["coins"];
			$args[] = time() - $ses->onSince;
			$args[] = $ses->onSince;
			$ses->onSince = time();
		}
		$conn->query($query, MysqlConnection::RAW, ...$args);
	}
}
