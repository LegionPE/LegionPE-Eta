<?php

namespace legionpe\games\parkour;

use legionpe\MysqlConnection;
use legionpe\session\Session;

class ParkourSessionData{
	/** @var Session */
	private $session;
	/** @var \legionpe\LegionPE */
	private $main;
	/** @var int */
	private $progress = 0;
	/** @var int */
	private $completions = 0;
	private $falls = 0;
	private $tmpFalls = 0;
	/** @var bool */
	private $changed = false;
	/** @var null|ParkourRace */
	public $race = null;
	public $lastClaimCoins = 0;
	public function __construct(Session $session){
		$this->session = $session;
		$this->main = $session->getMain();
		$result = $this->main->getMySQLi()->query("SELECT progress,completions,falls,tmpfalls FROM parkour WHERE uid=%d;", MysqlConnection::ASSOC, $session->getUID());
		if(is_array($result)){
			$this->progress = (int) $result["progress"];
			$this->completions = (int) $result["completions"];
			$this->falls = (int) $result["falls"];
			$this->tmpFalls = (int) $result["tmpfalls"];
		}
	}
	/**
	 * @return int
	 */
	public function getProgress(){
		return $this->progress;
	}
	/**
	 * @param int $progress
	 */
	public function setProgress($progress){
		$this->progress = $progress;
		$this->changed = true;
	}
	/**
	 * @return int
	 */
	public function getCompletions(){
		return $this->completions;
	}
	/**
	 * @param int $completions
	 */
	public function setCompletions($completions){
		$this->completions = $completions;
		$this->changed = true;
	}
	/**
	 * @return int
	 */
	public function getFalls(){
		return $this->falls;
	}
	/**
	 * @param int $falls
	 */
	public function setFalls($falls){
		$this->falls = $falls;
		$this->changed = true;
	}
	public function getTmpFalls(){
		return $this->tmpFalls;
	}
	public function addTmpFalls(){
		$this->tmpFalls++;
		$this->changed = true;
	}
	public function resetTmpFalls(){
		$this->tmpFalls = 0;
		$this->changed = true;
	}
	public function update(){
		if($this->changed){
			$this->main->getMySQLi()->query("INSERT INTO parkour(uid,progress,completions,falls,tmpfalls)VALUES({$this->session->getUID()},$this->progress,$this->completions,$this->falls,$this->tmpFalls)ON DUPLICATE KEY UPDATE progress=$this->progress,completions=$this->completions,falls=$this->falls,tmpfalls=$this->tmpFalls;", MysqlConnection::RAW);
		}
	}
	/**
	 * @return Session
	 */
	public function getSession(){
		return $this->session;
	}
	public function __toString(){
		return $this->session->__toString();
	}
}
