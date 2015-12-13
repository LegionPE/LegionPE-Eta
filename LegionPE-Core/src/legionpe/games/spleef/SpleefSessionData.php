<?php

namespace legionpe\games\spleef;

use legionpe\MysqlConnection;
use legionpe\session\Session;

class SpleefSessionData{
	/** @var SpleefGame */
	private $game;
	/** @var Session */
	private $session;
	private $arena = 0;
	private $spectating = false;
	private $data = [];
	private $needChange = false;
	public function __construct(SpleefGame $game, Session $session){
		$this->game = $game;
		$this->session = $session;
		$this->data = $game->getMain()->getMySQLi()->query("SELECT * FROM spleef WHERE uid=%d;", MysqlConnection::ASSOC, $session->getUID());
		if(!is_array($this->data)){
			$this->data = $this->getDefaultMysqlDataArray();
		}
		else{
			foreach(array_keys($this->data) as $k){
				$this->data[$k] = (int) $this->data[$k];
			}
		}
	}
	public function getArena(){
		return $this->game->getArena($this->arena);
	}
	/**
	 * @param int $id
	 * @param bool $spec
	 */
	public function joinArena($id, $spec){
		$this->arena = $id;
		$this->spectating = $spec;
	}
	public function quitArena(){
		$this->arena = 0;
		$this->spectating = false;
	}
	/**
	 * @return bool
	 */
	public function isInArena(){
		return $this->arena > 0;
	}
	public function isPlaying(){
		return $this->isInArena() and !$this->spectating;
	}
	/**
	 * @return bool
	 */
	public function isSpectating(){
		return $this->spectating;
	}
	/**
	 * @return Session
	 */
	public function getSession(){
		return $this->session;
	}
	public function getDefaultMysqlDataArray(){
		return [
			"uid" => $this->session->getUID(),
			"wins" => 0,
			"losses" => 0,
			"draws" => 0
		];
	}
	public function getWins(){
		return $this->data["wins"];
	}
	public function getLosses(){
		return $this->data["losses"];
	}
	public function getDraws(){
		return $this->data["draws"];
	}
	public function addWin(){
		$wins = ++$this->data["wins"];
		$this->needChange = true;
		$this->session->tell("You totally won $wins match(es).");
		return $wins;
	}
	public function addLoss(){
		$losses = ++$this->data["losses"];
		$this->needChange = true;
		$this->session->tell("You totally lost $losses match(es).");
		return $losses;
	}
	public function addDraw(){
		$draws = ++$this->data["draws"];
		$this->needChange = true;
		$this->session->tell("You totally have $draws draw(s).");
		return $draws;
	}
	public function update(){
		$this->game->getMain()->getMySQLi()->query(
			'INSERT INTO spleef(uid,wins,losses,draws,current_streak)VALUES(%d,%d,%d,%d,%d)ON DUPLICATE KEY UPDATE wins=VALUES(wins),losses=VALUES(losses),draws=VALUES(draws),current_streak=VALUES(current_streak);',
			MysqlConnection::RAW, (int) $this->session->getUID(), (int) $this->getWins(), (int) $this->getLosses(), (int) $this->getDraws(), 0);
	}
	public function __toString(){
		return $this->session->__toString();
	}
}
