<?php

namespace legionpe\games\kitpvp;

use legionpe\config\Settings;
use legionpe\MysqlConnection;
use legionpe\session\Session;

class PvpSessionData{
	/** @var PvpGame */
	private $game;
	/** @var int */
	private $origGm;
	/** @var int */
	private $uid;
	/** @var Session */
	private $session;
	private $needSave = false;
	private $data;
	private $isUpdate;
	public $streakCnt = 0;
	public $damageImmunity = -1;
	/** @var int */
	public $tpRequestTo = -1;
	private $friends = [];
	/** @var PvpKit[]  */
	private $kits = [];
	private $editingKit;
	public $isGoingToBuy = null;
	public $invulnerable = false;

	/**
	 * @param PvpGame $game
	 * @param int $origGm
	 * @param int $uid
	 * @param Session $session
	 * @param array $data
	 */
	public function __construct(PvpGame $game, $origGm, $uid, Session $session, array $data = null){
		$this->game = $game;
		$this->origGm = $origGm;
		$this->uid = $uid;
		$this->session = $session;
		if(is_array($data)){
			foreach(array_keys($data) as $key){
				$data[$key] = (int) $data[$key];
			}
			$this->data = $data;
			$this->isUpdate = true;
			$results = $game->getMain()->getMySQLi()->query("SELECT IF(smalluid=$uid,largeuid,smalluid)AS other FROM kitpvp_friends WHERE(smalluid=$uid OR largeuid=$uid)AND type=%d;", MysqlConnection::ALL, PvpGame::TYPE_FRIEND);
			foreach($results as $row){
				$this->friends[] = (int) $row["other"];
			}
			$results = $game->getMain()->getMySQLi()->query("SELECT * FROM kitpvp_kits WHERE uid=$uid", MysqlConnection::ALL);
			foreach($results as $row){
				$this->kits[$row["kitid"]] = PvpKit::fromAssoc($row);
			}
		}
		else{
			$this->data = $game->getDefaultMysqlDataArray($uid);
			$this->isUpdate = false;
		}
		$this->editingKit = $this->getKitIdInUse();
		if(!isset($this->kits[$this->editingKit])){
			$this->kits[$this->editingKit] = PvpKit::getDefault($uid, $this->editingKit);
		}
	}

	public function getUID(){
		return $this->uid;
	}
	public function getSession(){
		return $this->session;
	}

	public function getKills(){
		return $this->data["kills"];
	}
	public function setKills($kills){
		$this->data["kills"] = $kills;
		if($kills === 500){
			$this->session->tell("You are now an experienced KitPvP player! You no longer need the 8 eggs for new players.");
		}
		$this->needSave = true;
	}
	public function incrementKills($diff = 1){
		$this->setKills($this->data["kills"] + $diff);
	}
	public function getDeaths(){
		return $this->data["deaths"];
	}
	public function setDeaths($deaths){
		$this->data["deaths"] = $deaths;
		$this->needSave = true;
	}
	public function incrementDeaths($diff = 1){
		$this->setDeaths($this->data["deaths"] + $diff);
	}
	public function setKitIdInUse($kitId){
		$this->data["kit"] = $kitId;
		$this->needSave = true;
	}
	public function getKitIdInUse(){
		return $this->data["kit"];
	}
	public function getKitInUse(){
		return $this->getKitById($this->getKitIdInUse());
	}
	public function getKitById($kid, $nonNull = false, &$shouldNull = false){
		$shouldNull = false;
		if(isset($this->kits[$kid])){
			return $this->kits[$kid];
		}
		$shouldNull = true;
		return $nonNull ? PvpKit::getDefault($this->uid, $kid):null;
	}
	public function getKits(){
		return $this->kits;
	}
	/**
	 * @param int $id
	 */
	public function setEditingKitId($id){
		if(!isset($this->kits[$id])){
			var_dump($this->getKitById($id));
			throw new \OutOfBoundsException;
		}
		$this->editingKit = $id;
	}
	/**
	 * @return int
	 */
	public function getEditingKitId(){
		return $this->editingKit;
	}
	public function getEditingKit(){
		return $this->kits[$this->getEditingKitId()];
	}
	public function addKit($kid, PvpKit $kit){
		if(isset($this->kits[$kid])){
			throw new \RuntimeException("Kit $kid already exists for player $this->session");
		}
		$this->kits[$kid] = $kit;
		$kit->updated = true;
	}
	public function havingMaxKits(){
		return count($this->kits) >= Settings::kitpvp_maxKits($this->session->getRank());
	}

	public function isUsingBowKit(){
		return (bool) ($this->data["bow"] & 0xF0);
	}
	public function setIsUsingBowKit($bow){
		if($bow){
			$this->data["bow"] |= 0x10;
		}
		else{
			$this->data["bow"] &= 0x0F;
		}
		$this->needSave = true;
	}

	public function getBowLevel(){
		return $this->data["bow"] & 0x0F;
	}
	public function setBowLevel($newLevel){
		$this->data["bow"] &= 0xF0;
		$this->data["bow"] |= ($newLevel & 0x0F);
		$this->needSave = true;
	}

	public function getMaxStreak(){
		return $this->data["maxstreak"];
	}
	public function updateMaxStreak($newStreak){
		$max = max($this->data["maxstreak"], $newStreak);
		if($max !== $this->data["maxstreak"]){
			$this->data["maxstreak"] = $max;
			$this->needSave = true;
		}
	}

	/**
	 * @return int
	 */
	public function getStreakCnt(){
		return $this->streakCnt;
	}
	/**
	 * @param int $streakCnt
	 */
	public function setStreakCnt($streakCnt){
		$this->streakCnt = $streakCnt;
		$this->updateMaxStreak($streakCnt);
	}

	public function close(){
		$this->update();
	}
	public function update($force = false){
		if($this->needSave or $force){
			// stats
			if($this->isUpdate){
				$this->game->getMain()->getMySQLi()->query("UPDATE kitpvp SET kills=%d,deaths=%d,kit=%d,bow=%d,maxstreak=%d WHERE uid=%d;",
					MysqlConnection::RAW, $this->getKills(), $this->getDeaths(), $this->getKitIdInUse(), $this->data["bow"], $this->data["maxstreak"], $this->getUID()
				);
			}
			else{
				$this->game->getMain()->getMySQLi()->query("INSERT INTO kitpvp(uid,kills,deaths,kit,bow,maxstreak)VALUES(%d,%d,%d,%d,%d,%d)",
					MysqlConnection::RAW, $this->getUID(), $this->getKills(), $this->getDeaths(), $this->getKitIdInUse(), $this->data["bow"], $this->data["maxstreak"]
				);
			}
			$this->isUpdate = true;
		}
		// kits
		foreach($this->kits as $kit){
			if($kit->updated or $force){
				$kit->updateToMysql($this->game->getMain()->getMySQLi());
			}
		}
		$this->needSave = false;
	}
	/**
	 * @return int
	 */
	public function getOriginalGamemode(){
		return $this->origGm;
	}
	public function areFriends($uid){
		return in_array($uid, $this->friends);
	}
	public function addToFriends($uid){
		$this->friends[] = $uid;
	}
	public function rmFromFriends($uid){
		$key = array_search($uid, $this->friends);
		if($key !== false){
			unset($this->friends[$key]);
			return true;
		}
		return false;
	}
	public function countFriends(){
		return count($this->friends);
	}
}
