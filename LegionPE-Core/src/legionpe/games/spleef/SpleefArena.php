<?php

namespace legionpe\games\spleef;

use legionpe\config\Settings;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\item\Snowball;

class SpleefArena{
	/** @var SpleefGame */
	private $game;
	/** @var int */
	private $id;
	/** @var SpleefArenaConfig */
	private $config;
	/** @var SpleefSessionData[] */
	private $players = [];
	/** @var SpleefSessionData[] */
	private $specs = [];
	/** @var int */
	private $ticks;
	/** @var bool */
	private $state; // playing: true; waiting: false
	public function __construct(SpleefGame $game, $id){
		$this->game = $game;
		$this->config = Settings::spleef_getArenaConfig($this->id = $id, $game->getMain()->getServer());
	}
	public function reset(){
		foreach($this->players as $player){
			$this->kick($player, "match ended", false);
		}
		foreach($this->specs as $spec){
			$this->kick($spec, "match ended", false);
		}
		$this->config->build();
		$this->players = [];
		$this->specs = [];
		$this->state = false;
	}
	public function startMatch(){
		/**
		 * @var int $i
		 * @var SpleefSessionData $player
		 */
		$values = array_values($this->players);
		shuffle($values);
		foreach(array_values($values) as $i => $player){
			$loc = $this->config->playerStartLocs[$i];
			$player->getSession()->getPlayer()->teleport($loc, $loc->yaw, -90);
		}
		$this->state = true;
		$this->ticks = $this->config->maxGameTicks;
	}
	public function join(SpleefSessionData $data){
		$data->joinArena($this->id, false);
		$this->players[$data->getSession()->getUID()] = $data;
		$this->broadcast("{$data->getSession()} joined the match! There are now " . count($this->players) . " players in the arena.");
		$data->getSession()->teleport($this->config->playerPrepLoc);
		$this->giveTools($data->getSession()->getPlayer()->getInventory(), $data->getSession()->getRank());
		Settings::spleef_updateArenaSigns($this);
		if(count($this->players) < $this->config->minPlayers){
			return;
		}
		if(count($this->players) === $this->config->minPlayers){
			$this->ticks = $this->config->maxWaitTicks;
		}
		elseif($this->ticks < $this->config->minWaitTicks or count($this->players) === $this->config->getMaxPlayers()){
			$this->ticks = $this->config->minWaitTicks;
			$this->broadcastTime();
		}
	}
	public function spectate(SpleefSessionData $data){
		$data->joinArena($this->id, true);
		$this->specs[$data->getSession()->getUID()] = $data;
		$this->broadcastSpecs($data->getSession() . " is now spectating the match!");
		$data->getSession()->teleport($this->config->spectatorSpawnLoc);
	}
	public function kick(SpleefSessionData $data, $reason, $checkPlayers = true){
		if($data->isSpectating()){
			unset($this->specs[$data->getSession()->getUID()]);
		}
		elseif($checkPlayers){
			unset($this->players[$data->getSession()->getUID()]);
			if(count($this->players) === 1){
				/** @var SpleefSessionData $winner */
				$winner = array_values($this->players)[0];
				$award = 16 * Settings::coinsFactor($winner->getSession());
				$this->broadcast("The match has ended. {$winner->getSession()} won the match and is awarded with $award coins.");
				$winner->getSession()->setCoins($coins = $winner->getSession()->getCoins() + $award);
				$winner->getSession()->tell("You now have $coins coins.");
				$winner->addWin();
				$this->game->getDefaultChatChannel()->broadcast("$winner won a match in $this!");
				$this->reset();
			}
			Settings::spleef_updateArenaSigns($this);
		}
		$data->quitArena();
		$data->getSession()->tell("You left {$this->config->name}: $reason.");
		$data->getSession()->teleport(Settings::spleef_spawn($this->game->getMain()->getServer()));
	}
	public function tick(){
		if($this->state){ // playing
			$this->ticks--;
			$this->broadcastTime();
			if($this->ticks === 0){
				if(($cnt = count($this->players)) > 1){
					$this->broadcast("The match has ended. There are no winners.");
					$this->broadcastSpecs("The $cnt survivors will share the award of 20 coins.");
					$mean = 20 / $cnt;
					foreach($this->players as $player){
						$get = round($mean * Settings::coinsFactor($player->getSession()), 2);
						$player->getSession()->setCoins($coins = $player->getSession()->getCoins() + $get);
						$player->getSession()->tell("You are awarded with $get coins. You now have $coins coins.");
						$player->addDraw();
					}
					$this->game->getDefaultChatChannel()->broadcast("Match in $this ended with a draw with $cnt survivors!");
				}
				$this->reset();
				return;
			}
			foreach($this->players as $player){
				if($player->getSession()->getPlayer()->getFloorY() < ($this->config->lowestY - 1)){
					$this->kick($player, "you fell out of the arena");
					$this->broadcast("{$player->getSession()} has fallen!");
					$player->addLoss();
				}
				if(count($this->players) === 0){
					$this->reset();
					return; // not sure what will happen. ConcurrentModificationException?
				}
			}
		}
		elseif(count($this->players) > 1){
			$this->ticks--;
			$this->broadcastTime();
			if($this->ticks === 0){
				$this->broadcast("The match now starts!");
				$this->startMatch();
			}
		}
	}
	public function broadcast($msg){
		$this->broadcastSpecs($msg);
		$this->broadcastPlayers($msg);
	}
	public function broadcastSpecs($msg){
		foreach($this->specs as $spec){
			$spec->getSession()->tell($msg);
		}
	}
	public function broadcastPlayers($msg){
		foreach($this->players as $player){
			$player->getSession()->tell($msg);
		}
	}
	public function broadcastTime(){
		if($this->state){
			if($this->ticks === 100 or $this->ticks === 200 or $this->ticks === 300 or $this->ticks === 400 or $this->ticks === 600 or $this->ticks === 1200){
				$this->broadcast(sprintf("%d seconds left!", $this->ticks / 20));
			}
		}
		elseif($this->ticks === 100 or $this->ticks === 200 or $this->ticks === 300 or $this->ticks === 400 or $this->ticks === 600){
			$this->broadcast(sprintf("%d seconds before the match starts!", $this->ticks / 20));
		}
	}
	public function isPlaying(){
		return $this->state;
	}
	public function countPlayers(){
		return count($this->players);
	}
	public function getPlayers(){
		return $this->players;
	}
	public function getSpectators(){
		return $this->specs;
	}
	public function isFull(){
		return count($this->players) >= $this->config->getMaxPlayers();
	}
	public function getGame(){
		return $this->game;
	}
	public function getId(){
		return $this->id;
	}
	public function getConfig(){
		return $this->config;
	}
	public function __toString(){
		return $this->config->name;
	}
	private function giveTools(PlayerInventory $inv, /** @noinspection PhpUnusedParameterInspection */ $rank){
		$inv->getHolder()->setHealth(20);
		$inv->remove(Item::get(Item::SNOWBALL));
		$inv->addItem(...$this->config->playerItems/*[($rank & 0b1100) >> 2]*/); // TODO support shops
		$inv->addItem(new Snowball(0, Settings::easter_getSnowballCount($rank)));
	}
}
