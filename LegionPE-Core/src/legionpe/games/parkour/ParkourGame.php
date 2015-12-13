<?php

namespace legionpe\games\parkour;

use legionpe\chat\Channel;
use legionpe\config\Settings;
use legionpe\games\Game;
use legionpe\LegionPE;
use legionpe\MysqlConnection;
use legionpe\session\Session;
use pocketmine\block\Ladder;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\ItemBlock;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class ParkourGame implements Game{
	private $main;
	private $ch;
	/** @var ParkourSessionData[] */
	private $data = [];
	public function __construct(LegionPE $main){
		$this->main = $main;
		$this->ch = $this->main->getChannelManager()->joinChannel($main, "parkour", Channel::CLASS_MODULAR);
		$this->getMain()->getServer()->getPluginManager()->registerEvents($this, $main);
	}
	public function onDisable(){

	}
	public function getMain(){
		return $this->main;
	}
	public function getName(){
		return "Parkour";
	}
	public function getSelectionItem(){
		return new ItemBlock(new Ladder);
	}
	public function onJoin(Session $session){
		$this->data[$session->getUID()] = new ParkourSessionData($session, $this->main);
		$session->teleport($pos = Settings::parkour_checkpoint_startPos($this->data[$session->getUID()]->getProgress(), $session->getMain()->getServer()));
		$session->getPlayer()->setSpawn($pos);
		return true;
	}
	public function onQuit(Session $session, $force){
		if(isset($this->data[$uid = $session->getUID()])){
			$this->data[$uid]->update();
			if($this->data[$uid]->race instanceof ParkourRace){
				$this->data[$uid]->race->quit($session);
			}
			unset($this->data[$uid]);
		}
		return true;
	}
	public function getSessionId(){
		return Session::SESSION_GAME_PARKOUR;
	}
	public function getDefaultChatChannel(){
		return $this->ch;
	}
	public function getDefaultCommands(){
		return [
			[
				"name" => "restart",
				"description" => "Parkour: start from spawn again",
				"usage" => "Parkour: /restart"
			],
//			[
//				"name" => "race",
//				"description" => "Parkour: race with another player",
//				"usage" => "Parkour: /race start|quit|join [player]"
//			]
		];
	}
	public function formatChat(Session $session, $msg, $isACTION){
		$tag = "";
		if(isset($this->data[$uid = $session->getUID()]) and ($completions = $this->data[$uid]->getCompletions()) > 0){
			$tag = "[§e" . $completions . "§7]";
		}
		$color = ($session->getRank() & (Settings::RANK_SECTOR_IMPORTANCE | Settings::RANK_SECTOR_PERMISSION) & ~Settings::RANK_IMPORTANCE_TESTER) ? "§f":"";
		return $isACTION ? "* $tag$session $color$msg":"$tag$session: $color$msg";
	}
	public function saveSessionsData(){
		foreach($this->data as $data){
			$data->update();
		}
	}
	public function onStats(Session $session, array $args){
		if(isset($args[0]) and strtolower($args[0]) === "top"){
			$this->saveSessionsData();
			$rows = 5;
			if(isset($args[1]) and is_numeric($args[1])){
				$rows = intval($args[1]);
			}
			$result = $this->main->getMySQLi()->query("SELECT (SELECT names FROM players WHERE uid=parkour.uid)AS names,completions FROM parkour ORDER BY completions DESC LIMIT %d;", MysqlConnection::ALL, $rows);
			$output = "";
			foreach($result as $i => $row){
				if(!isset($row["completions"])){
					break;
				}
				$output .= "#" . ($i + 1) . ") " . substr($row["names"], 0, -1) . ": " . $row["completions"] . " time(s)\n";
			}
			return $output;
		}
		if(isset($this->data[$uid = $session->getUID()])){
			$data = $this->data[$uid];
			$checkPt = $data->getProgress();
			if($checkPt === 0){
				$progress = "at spawn";
			}
			else{
				$progress = "checkpoint $checkPt";
			}
			$completions = "for " . ($compNo = $data->getCompletions()) . " times";
			if($compNo === 2){
				$completions = "twice";
			}
			elseif($compNo === 1){
				$completions = "once";
			}
			$output = "Your checkpoint is $progress.\n";
			if($compNo > 0){
				$output .= "You have finished the whole parkour $completions.\n";
			}
			$falls = $data->getFalls();
			if($falls > 0){
				$output .= "You have totally $falls fall(s).";
			}
			else{
				$output .= "You have never finished the whole parkour.";
			}
			return $output;
		}
		return "Error: Session data not initialized!";
	}
	public function testCommandPermission(Command $cmd, Session $session){
		switch($cmd->getName()){
			case "restart":
				return true;
			case "race":
				return true;
		}
		return false;
	}
	public function onCommand(Command $cmd, array $args, Session $session){
		$data = $this->data[$session->getUID()];
		switch($cmd->getName()){
			case "restart":
				$data->setProgress(0);
				$session->teleport($pos = Settings::parkour_checkpoint_startPos(0, $this->getMain()->getServer()));
				$session->getPlayer()->setSpawn($pos);
				$data->resetTmpFalls();
				$session->tell("Your parkour progress has been reset.");
				return;
			case "race":
				if(!isset($args[0])){
					$args = ["help"];
				}
				switch($sub = array_shift($args)){
					case "start":
						if($data->race !== null){
							$session->tell("You can't start a race, because you are already in a race started by {$data->race->starter}. `/race quit` to quit it.");
							break;
						}
						$isOpen = true;
						while(isset($args[0])){
							switch(array_shift($args)){
								case "close":
									$isOpen = false;
									break;
							}
						}
						if($isOpen){
							foreach($this->data as $dat){
								$dat->getSession()->tell("$session started an open race. Use `/race join $session` to join!");
							}
						}
						$data->race = new ParkourRace($this, $session, $isOpen);
						$session->tell("Race has been created. Do `/race begin` when all players have joined.");
						break;
					case "join":
						if($data->race !== null){
							$session->tell("You can't join a race because you are already in a race started by {$data->race->starter}. `/race quit` to quit it.");
							break;
						}
						if(!isset($args[0])){
							$session->tell("Usage: /race join <starter>");
							break;
						}
						$name = array_shift($args);
						$starter = $this->getMain()->getSessions()->getSession($name);
						if(!($starter instanceof Session)){
							$session->tell("Player $name not found!");
							break;
						}
						$starterData = $this->getSessionData($starter);
						if(!($starterData instanceof ParkourSessionData)){
							$session->tell("Player $starter is not in parkour!");
							break;
						}
						$race = $starterData->race;
						if(!($race instanceof ParkourRace)){
							$session->tell("$starter isn't holding a race!");
							$session->tell("Usage: /race join <starter>");
							break;
						}
						if($race->isStarted()){
							$session->tell("The race held by $starter was already started!");
							break;
						}
						if(!$race->isInvited($session)){
							$session->tell("The race held by $starter is not open, and you aren't invited!");
							break;
						}
						$race->join($session);
						break;
					case "invite":
						if(!isset($args[0])){
							$session->tell("Usage: /race invite <player>");
						}
						$name = array_shift($args);
						if(!($data->race instanceof ParkourRace)){
							$session->tell("You are not in a race!");
							break;
						}
						if($data->race->starter !== $session){
							$session->tell("You are not the starter of the race; you don't have permission to invite a player.");
							break;
						}
						if($data->race->isStarted()){
							$session->tell("The race is already started!");
							break;
						}
						$data->race->invite($name);
						$session->tell("$name has been invited.");
						$player = $this->getMain()->getServer()->getPlayerExact($name);
						if(!($player instanceof Player)){
							$session->tell(TextFormat::YELLOW . "Warning: $name is not online! Make sure you typed the full name of the player!");
						}
						elseif(!(($invitedSession = $this->getMain()->getSessions()->getSession($player)) instanceof Session)){
							$session->tell(TextFormat::YELLOW . "Warning: $name is not online! Make sure you typed the full name of the player!");
						}
						else{
							if(!isset($this->data[$invitedSession->getUID()])){
								$session->tell(TextFormat::YELLOW . "Warning: $invitedSession isn't in parkour!");
							}
							elseif($this->data[$invitedSession->getUID()]->race instanceof ParkourRace){
								$session->tell(TextFormat::YELLOW . "Warning: $invitedSession is in another race!");
							}
							$invitedSession->tell("You have been invited to a parkour race held by $session.");
							break;
						}
						break;
					case "begin":
						if(!($data->race instanceof ParkourRace)){
							$session->tell("You aren't in a race. Use `/race start` to start a race!");
							break;
						}
						if($data->race->starter !== $session){
							$session->tell("You don't have permission to start the race. Ask {$data->race->starter} to begin the race!");
							break;
						}
						$data->race->begin();
						break;
					case "quit":
						if($data->race === null){
							$session->tell("You aren't in a race!");
							break;
						}
						$data->race->quit($session);
						break;
					default:
						$session->tell(str_repeat("~", 34));
						$session->tell("Usage: /race start|invite|join|quit");
						$session->tell("/race start [open]");
						$session->tell("/race invite <exact name>");
						$session->tell("/race join <starter>");
						$session->tell("/race quit");
						break;
				}
				return;
		}
		return;
	}
	public function onBlockTouch(PlayerInteractEvent $event){
		$session = $this->main->getSessions()->getSession($p = $event->getPlayer());
		if(isset($this->data[$session->getUID()])){
			$data = $this->data[$session->getUID()];
			$sign = Settings::parkour_checkpoint_signs($event->getBlock());
			if($sign === -1){
				if(is_array($coords = Settings::parkour_teleportSign($event->getBlock(), $session->getPlayer()))){
					foreach($coords as $c){
						$p->teleport($c);
					}
				}
				return;
			}
			if($sign === $data->getProgress()){
				$session->tell("Checkpoint unchanged.");
				return;
			}
			if($sign === 0){
				if(microtime(true) - $data->lastClaimCoins < 10){
					$session->tell("Don't spam the sign!");
					return;
				}
				$coins = 15;
				$tmpFalls = $data->getTmpFalls();
				$tmpFalls = max(min($tmpFalls, 50), 0);
				$tmpFalls /= 5;
				$award = round(($coins - $tmpFalls) * Settings::coinsFactor($session), 2);
				$session->tell("You have completed parkour! You are awarded with %d coins!", $award);
				$coins = $session->getCoins() + $award;
				$session->setCoins($coins);
				$session->tell("You now have $coins coins!");
				$data->setCompletions($data->getCompletions() + 1);
				$data->setProgress(0);
				$data->resetTmpFalls();
				if($data->race instanceof ParkourRace){
					$data->race->broadcastProgress($data->getSession(), 0);
				}
			}
			else{
				$session->tell("You reached checkpoint $sign!");
				$data->setProgress($sign);
				if($data->race instanceof ParkourRace){
					$data->race->broadcastProgress($data->getSession(), 0);
				}
			}
			$session->getPlayer()->setSpawn($pos = Settings::parkour_checkpoint_startPos($sign, $this->getMain()->getServer()));
			if($data->getProgress() === 0){
				$session->teleport($pos);
			}
			$event->setCancelled();
		}
	}
	public function onMove(Session $session, PlayerMoveEvent $event){
		if(Settings::parkour_isFallen($session->getPlayer())){
			$data = $this->data[$session->getUID()];
			$session->teleport(Settings::parkour_checkpoint_startPos($data->getProgress(), $this->getMain()->getServer()));
			$data->setFalls($data->getFalls() + 1);
			$data->addTmpFalls();
		}
	}
	public function onRespawn(PlayerRespawnEvent $event, Session $session){
		$event->setRespawnPosition(Settings::parkour_checkpoint_startPos($this->data[$session->getUID()]->getProgress(), $session->getMain()->getServer()));
	}
	public function getSessionData(Session $session){
		return isset($this->data[$session->getUID()]) ? $this->data[$session->getUID()] : null;
	}
	public function countPlayers(){
		return count($this->data);
	}
	public function __toString(){
		return $this->getName();
	}
	/**
	 * @param Session $ses
	 * @param int $from
	 * @param int $to
	 * @return bool
	 */
	public function onInventoryStateChange(Session $ses, $from, $to){
		return true;
	}
}
