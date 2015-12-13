<?php

namespace legionpe\session;

use legionpe\config\Settings;
use legionpe\MysqlConnection;

class SpamDetector{
	private $ses;
	/** @var float|null */
	private $lastMsgTime = null;
	/** @var bool */
	private $bypass;
	/** @var int[] */
	private $lengths = [10, 10, 10, 10];
	/** @var string[] */
	private $lastMsgs = ["", "", ""];
	private $ghMsgCooldown = 0;
	public function __construct(Session $session){
		$this->ses = $session;
		$this->bypass = ($session->getRank() & Settings::RANK_PERM_SPAM) > 0;
	}
	public function onMessage($msg){
		if($this->onMsg($msg)){
			$this->lastMsgTime = microtime(true);
			return true;
		}
		return false;
	}
	private function onMsg($msg){
		if($msg === "SPAM!! SPAM!! SPAM!! SPAM!! SPAM!! SPAM!! SPAM!! SPAM!!" and microtime(true) - $this->ghMsgCooldown > 10){
//			$this->ses->warnMods();
			$this->ses->getMysqlSession()->data["modswarns"]++;
			// TODO
			$this->ses->getMain()->getMySQLi()->query("INSERT INTO ipbans (ip, msg, issuer, creation, length) VALUES (%s, %s, %s, from_unixtime(%d), %d);", MysqlConnection::RAW, $this->ses->getPlayer()->getAddress(), "Using Ghost Hack mod, detected by spamming", "LegionPE SpamDetective", time(), 3600 * 24 * 7);
			$this->ses->getMain()->getServer()->broadcast("$this->ses ({$this->ses->getPlayer()->getAddress()}) said the Ghost Hack spam message and is IP-banned for 7 days!", "pocketmine.broadcast.admin");
			return false;
		}
		if(strtoupper($msg) === $msg and strtolower($msg) !== $msg){
			$this->ses->tell("Abusive caps are disallowed.");
			return false;
		}
		if($this->lastMsgTime === null){
			return true;
		}
		if($this->bypass){
			return true;
		}
		if(microtime(true) - $this->lastMsgTime < 2.5){
			$this->ses->tell("You don't realize that sending chat messages that quick is considered as spamming?");
			return false;
		}
		$s = array_shift($this->lengths);
		$cnt = 1;
		$this->lengths[] = strlen($msg);
		foreach($this->lengths as $l){
			$cnt++;
			$s += $l;
		}
		if($s <= $cnt + 1){
			$this->ses->tell("Your recent messages are too short!");
			return false;
		}
		$last = array_shift($this->lastMsgs);
		$this->lastMsgs[] = $msg;
		foreach($this->lastMsgs as $m){
			if($m !== $last){
				return true;
			}
		}
		$this->ses->tell("No spamming.");
		return false;
	}
}
