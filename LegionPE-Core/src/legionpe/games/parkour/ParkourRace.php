<?php

namespace legionpe\games\parkour;

use legionpe\session\Session;
use legionpe\utils\MUtils;
use pocketmine\Player;

class ParkourRace{
	/** @var ParkourGame */
	public $game;
	/** @var Session */
	public $starter;
	/** @var bool */
	public $isOpen;
	/** @var string[] */
	public $invitations = [];
	/** @var Session[] */
	public $players = [];
	/** @var bool[] */
	private $won = [];
	public $startTime = -1;
	public function __construct(ParkourGame $game, Session $player, $isOpen){
		$this->game = $game;
		$this->starter = $player;
		$this->players[$player->getUID()] = $player;
		$this->isOpen = $isOpen;
	}
	public function join(Session $session){
		if(isset($this->players[$session->getUID()])){
			return false;
		}
		$data = $this->game->getSessionData($session);
		if($data->race instanceof self){
			return false;
		}
		$data->race = $this;
		$this->players[$session->getUID()] = $session;
		$this->broadcast("$session joined the race!");
		return true;
	}
	public function quit(Session $session){
		if(!isset($this->players[$session->getUID()])){
			return false;
		}
		$this->broadcast("$session quitted the race!");
		unset($this->players[$session->getUID()]);
		$this->game->getSessionData($session)->race = null;
		if($this->starter === $session){
			$this->broadcast("The starter of this race, $session, quitted the race, so the race has been forcefully ended.");
			foreach($this->players as $player){
				$this->game->getSessionData($player)->race = null;
			}
			$this->players = null; // garbage
		}
		if(isset($this->won[$session->getUID()])){
			unset($this->won[$session->getUID()]);
		}
		return true;
	}
	public function broadcast($msg, ...$args){
		if(count($args) > 0){
			$msg = sprintf($msg, ...$args);
		}
		foreach($this->players as $p){
			$p->tell($msg);
		}
	}
	public function isStarted(){
		return $this->startTime !== -1;
	}
	public function begin(){
		$this->broadcast("The race is now starting!");
		$this->startTime = microtime(true);
	}
	public function isInvited($name){
		if($this->isOpen){
			return true;
		}
		if($name instanceof Session){
			$name = $name->getPlayer()->getName();
		}
		elseif($name instanceof Player){
			$name = $name->getName();
		}
		return in_array(strtolower($name), $this->invitations);
	}
	public function invite($name){
		if($this->isInvited($name)){
			return false;
		}
		$this->invitations[] = strtolower($name);
		return true;
	}
	public function broadcastProgress(Session $session, $progress){
		if(isset($this->won[$session->getUID()])){
			return;
		}
		$diff = microtime(true) - $this->startTime;
		$time = MUtils::time_secsToString($diff);
		$this->broadcast("$session arrive at %s, spending %s!", $progress === 0 ? "the end":"checkpoint $progress", substr($time, 0, -2));
		if($progress === 0){
			$this->won[$session->getUID()] = true;
		}
		if(count($this->won) === count($this->players)){
			$this->broadcast("All players have finished. Race has ended.");
		}
	}
}
