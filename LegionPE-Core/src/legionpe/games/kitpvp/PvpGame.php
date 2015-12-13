<?php

namespace legionpe\games\kitpvp;

use legionpe\chat\Channel;
use legionpe\command\session\oldSessionCommand;
use legionpe\config\Settings;
use legionpe\games\Game;
use legionpe\LegionPE;
use legionpe\MysqlConnection;
use legionpe\session\Session;
use legionpe\utils\CallbackPluginTask;
use legionpe\utils\MUtils;
use legionpe\utils\SlappableHuman;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\entity\Arrow;
use pocketmine\entity\Entity;
use pocketmine\entity\Snowball;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\IronSword;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class PvpGame implements Game{
	const TYPE_BROKE = 0;
	const TYPE_FRIEND = 1;
	const TYPE_ACCEPTED = 1;
	const TYPE_WAITING_SMALL = 2;
	const TYPE_FROM_LARGE = 2;
	const TYPE_WAITING_LARGE = 3;
	const TYPE_FROM_SMALL = 3;
	private $main;
	private $pvpChannel;
	/** @var PvpGameChunkListener */
	private $chunkListener;
	/** @var PvpSessionData[] */
	private $playerData = [];
	public function __construct(LegionPE $main){
		$this->main = $main;
		$this->pvpChannel = $this->main->getChannelManager()->joinChannel($main, "kitpvp", Channel::CLASS_MODULAR);
		$this->main->getServer()->getPluginManager()->registerEvents($this, $this->main);
		Entity::registerEntity(PvpBowHolder::class, true);
		Entity::registerEntity(PvpKitModel::class, true);
		Entity::registerEntity(PvpUpgradeDummyShop::class, true);
		$this->getMain()->getServer()->getPluginManager()->registerEvents($this->chunkListener = $cl = new PvpGameChunkListener($this), $this->getMain());
		for($id = 1; $id <= 5; $id++){
			$loc = Settings::kitpvp_getNpcLocation($this->getMain()->getServer(), $id);
			$this->chunkListener->addModel($loc, $id);
		}
		foreach(Settings::kitpvp_getShopLocations($this->getMain()->getServer()) as $col => $loc){
			$cl->addShop($loc, $col);
		}
	}
	public function getMain(){
		return $this->main;
	}
	public function getName(){
		return "KitPvP";
	}
	public function getSelectionItem(){
		return new IronSword;
	}
	public function onJoin(Session $session){
		$uid = $session->getUID();
		$data = $this->main->getMySQLi()->query("SELECT * FROM kitpvp WHERE uid=$uid;", MysqlConnection::ASSOC);
		if(is_array($data)){
			$this->playerData[$uid] = new PvpSessionData($this, $session->getPlayer()->getGamemode(), $uid, $session, $data);
		}
		else{
			$this->playerData[$uid] = new PvpSessionData($this, $session->getPlayer()->getGamemode(), $uid, $session);
			$this->getMain()->getStats()->increment(LegionPE::TITLE_KITPVP_NEW_JOINS);
		}
		$session->tell("%s, welcome to KitPvP.", $session->getPlayer()->getName());
		$session->teleport($spawn = Settings::kitpvp_spawn($this->main->getServer()));
		foreach($this->chunkListener->getSpawnedModels() as $model){
			$model->spawnTo($session->getPlayer());
		}
		$session->getPlayer()->setSpawn($spawn);
//		$session->tell($this->onFriendInboxCommand($session));
		$this->getMain()->getStats()->increment(LegionPE::TITLE_KITPVP_JOINS);
		$this->giveKits($session);
		if($session->getPlayer()->getGamemode() === 0){
			$session->getPlayer()->setGamemode(2);
		}
		return true;
	}
	public function onQuit(Session $session, $force){
		if(isset($this->playerData[$session->getUID()])){
			$this->playerData[$session->getUID()]->close();
			unset($this->playerData[$session->getUID()]);
		}
		return true;
	}
	public function getSessionId(){
		return Session::SESSION_GAME_KITPVP;
	}
	public function formatChat(Session $session, $msg, $isACTION){
		$kills = $this->getKills($session);
		$tag = "§7";
		if($session->getMysqlSession()->data["notag"] === 1){
			goto no_tag;
		}
		$tag = $this->getTag($kills);
		if(strlen($tag) > 0){
			$tag = "[§b" . $tag . "§7]";
		}
		no_tag:
		$color = ($session->getRank() & (Settings::RANK_SECTOR_IMPORTANCE | Settings::RANK_SECTOR_PERMISSION)) ? "§f":"";
		if(!$isACTION){
			return $tag . ($kills > 0 ? ("[§c" . $kills . "§7]"):"") . "§f{$session->getPlayer()->getDisplayName()}§7: $color$msg";
		}
		return "* $tag" . "§f{$session->getPlayer()->getDisplayName()}§7 $color$msg";
	}
	public function getDefaultChatChannel(){
		return $this->pvpChannel;
	}
	public function getDefaultCommands(){
		return [
			[
				"name" => "friend",
				"description" => "KitPvP: Manage your PvP friends",
				"usage" => "KitPvP: /friend add|remove|list|outbox|inbox",
				"aliases" => ["f"]
			],
			[
				"name" => "tpa",
				"description" => "KitPvP: Accept a teleport request from another PvP player",
				"usage" => "KitPvP: /tpa <player>"
			],
			[
				"name" => "tpc",
				"description" => "KitPvP: Cancel a pending teleport request",
				"usage" => "KitPvP: /tpc"
			],
			[
				"name" => "tpd",
				"description" => "KitPvP: Deny a teleport request from another PvP player",
				"usage" => "KitPvP: /tpd <player> [message ...]"
			],
			[
				"name" => "tpr",
				"description" => "KitPvP: Send a teleport request to another PvP player",
				"usage" => "KitPvP: /tpr <player> [message ...]"
			],
		];
	}
	public function testCommandPermission(Command $cmd, Session $session){
		$name = $cmd->getName();
		if(in_array(strtolower($name), ["friend", "tpa", "tpc", "tpd", "tpr"])){
			return true;
		}
		return false;
	}
	public function onCommand(Command $cmd, array $args, Session $session){
		switch($cmd->getName()){
			case "friend":
				if(!isset($args[0])){
					$session->tell("Usage of /friend:\n" .
						"/friend add <name>: Add <name> to your friend list if he/she requested you to be friend, or send him/her a request if he/she didn't.\n" .
						"/friend remove <name>: If you are friends with <name>, you are no longer friends. If one of you sent a friend request to another, the request will be cancelled/denied. If you are not related at all, no warnings will be shown.\n" .
						"/friend list: View your friend list.\n" .
						"/friend inbox: View all requests sent to you." .
						"/friend outbox: View all requests you sent to other players.");
					return;
				}
				$session->tell($this->onFriendCommand($args, $session));
				return;
			case "tpa":
				if(Settings::kitpvp_isSafeArea($session->getPlayer())){
					$session->tell("You can't accept teleport requests from spawn!");
					return;
				}
				if(!isset($args[0])){
					if($cmd instanceof oldSessionCommand){
						$cmd->tellWrongUsage($session);
					}
					return;
				}
				$other = $this->getMain()->getSessions()->getSession($on = array_shift($args));
				if(!($other instanceof Session)){
					$session->tell("Player $on not found!");
					return;
				}
				if(!isset($this->playerData[$other->getUID()])){
					$session->tell("$other is no longer in KitPvP, so he can't teleport!");
					return;
				}
				if($this->playerData[$other->getUID()]->tpRequestTo !== $session->getUID()){
					$session->tell("$other cancelled/didn't send his teleport request to you.");
					return;
				}
				$this->playerData[$other->getUID()]->tpRequestTo = -1;
				$other->tell("You are going to be teleported to $session in a few seconds. Hold the handrail!");
				$session->tell("$other is going to be teleported to you in a few seconds. Don't stand near a cliff!");
				$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(new CallbackPluginTask($this->getMain(), function() use($session, $other){
					if($session->getPlayer() instanceof Player and $session->getPlayer()->isOnline() and $other->getPlayer() instanceof Player and $other->getPlayer()->isOnline()){
						// don't quit! don't crash me!
						$other->teleport($session->getPlayer());
					}
				}), 100);
				return;
			case "tpd":
				if(!isset($args[0])){
					if($cmd instanceof oldSessionCommand){
						$cmd->tellWrongUsage($session);
					}
					return;
				}
				$other = $this->getMain()->getSessions()->getSession($on = array_shift($args));
				if(!($other instanceof Session)){
					$session->tell("Player $on not found!");
					return;
				}
				if(!isset($this->playerData[$other->getUID()])){
					$session->tell("$other is no longer in KitPvP, so you don't have to do the dirty job to deny it! :D");
					return;
				}
				if($this->playerData[$other->getUID()]->tpRequestTo !== $session->getUID()){
					$session->tell("$other cancelled/didn't send his teleport request to you.");
					return;
				}
				$this->playerData[$other->getUID()]->tpRequestTo = -1;
				$other->tell("$session doesn't want you to teleport to him: \"%s\"", implode(" ", $args));
				$session->tell("Dirty job done; $session's teleport request to you has been denied.");
				return;
			case "tpr":
				if(!isset($args[0])){
					if($cmd instanceof oldSessionCommand){
						$cmd->tellWrongUsage($session);
					}
					return;
				}
				$other = $this->getMain()->getSessions()->getSession($on = array_shift($args));
				if(!($other instanceof Session)){
					$session->tell("Player $on not found!");
					return;
				}
				if(!$other->inSession($this)){
					$session->tell("$other is not in KitPvP!");
					return;
				}
				$data = $this->cancelTpRequest($session);
				$data->tpRequestTo = $other->getUID();
				$other->tell("$session has sent you a teleport request: " . implode(" ", $args));
				$session->tell("A teleport request has been sent to $other.");
				return;
			case "tpc":
				$this->cancelTpRequest($session, true);
				return;
		}
	}
	public function onStats(Session $session, array $args){
		if(isset($args[0])){
			$param = strtolower($args[0]);
			if($param === "top"){
				$this->saveSessionsData();
				$rows = 5;
				if(isset($args[1]) and is_numeric($args[1])){
					$rows = intval($args[1]);
				}
				$output = "";
				$result = $this->main->getMySQLi()->query("SELECT(SELECT names FROM players WHERE uid=kitpvp.uid)AS name,kills,deaths FROM kitpvp ORDER BY kills DESC LIMIT $rows", MysqlConnection::RAW);
				$i = 1;
				while(is_array($row = $result->fetch_assoc())){
					$output .= sprintf("#%d) %s (%d kills, %d deaths, %s K/D)\n", $i++, strstr($row["name"], "|", true), (int) $row["kills"], (int) $row["deaths"], (string) round(((int) $row["kills"]) / ((int) $row["deaths"]), 2)); // I'm not sure about the associative names
				}
				$result->close();
				return $output;
			}
			if($param === "ks"){
				$this->saveSessionsData();
				$rows = 5;
				if(isset($args[1]) and is_numeric($args[1])){
					$rows = intval($args[1]);
				}
				$output = "";
				$result = $this->main->getMySQLi()->query("SELECT(SELECT names FROM players WHERE uid=kitpvp.uid)AS name,maxstreak FROM kitpvp ORDER BY maxstreak DESC LIMIT $rows", MysqlConnection::ALL);
				foreach($result as $i => $row){
					$output .= "#" . ($i + 1) . ") " . strstr($row["name"], "|", true) . ": " . $row["maxstreak"] . " kills streak\n";
				}
				return $output;
			}
		}
		$data = $this->getPlayerData($session);
		if(!($data instanceof PvpSessionData)){
			return "Cannot find session data!";
		}
		return sprintf("Kills: %d\n" .
			"Deaths: %d\n" .
			"K/D ratio: %s\n" .
			"Highest killstreak: %d", $data->getKills(), $data->getDeaths(),
			($data->getDeaths() === 0) ? "N/A" : ((string) round($data->getKills() / $data->getDeaths(), 2)),
			$data->getMaxStreak());
	}
	/**
	 * @param EntityDamageEvent $event
	 * @priority NORMAL
	 * @ignoreCancelled false
	 */
	public function e_onDamage(EntityDamageEvent $event){
		if($event instanceof EntityDamageByEntityEvent){
			if($event->getDamager() instanceof SlappableHuman){
				return;
			}
		}
		$this->i_onDamage($event, $session);
		if(!$event->isCancelled()){
			if($session instanceof Session and isset($this->playerData[$session->getUID()])){
				$data = $this->playerData[$session->getUID()];
				if($data->damageImmunity !== -1 and microtime(true) - $data->damageImmunity < 0.5){
					$event->setCancelled(); // 0.5 second damage immunity
				}
				else{
					$data->damageImmunity = microtime(true);
				}
			}
		}
	}
	public function i_onDamage(EntityDamageEvent $event, &$victimSession){
		$victim = $event->getEntity();
		if(!($victim instanceof Player)){
			return;
		}
		$victimSession = $this->getMain()->getSessions()->getSession($victim);
		if(!$victimSession->inSession($this)){
			return;
		}
		$origCancelled = $event->isCancelled();
		$event->setCancelled(false);
		if(!($event instanceof EntityDamageByEntityEvent)){
			if($event->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION){
				$event->setCancelled();
				$victimSession->teleport(Settings::kitpvp_spawn($this->getMain()->getServer()));
				return;
			}
			if($event->getCause() === EntityDamageEvent::CAUSE_FALL){
				$fallCause = $victim->getLastDamageCause();
				if($fallCause instanceof EntityDamageByEntityEvent){
					if(isset($fallCause->_legionpeEta_timestamp) and microtime(true) - $fallCause->_legionpeEta_timestamp < 2){
						/** @noinspection PhpUndefinedFieldInspection */
						$event->_legionpeEta_fallCause = $fallCause;
					}
				}
			}
			return;
		}
		$victim = $event->getEntity();
		$damager = $event->getDamager();
		if(!($victim instanceof Player) or !($damager instanceof Player)){
			return;
		}
		$attackerSession = $this->main->getSessions()->getSession($damager);
		if(!$attackerSession->inSession($this)){
			$event->setCancelled($origCancelled);
			return;
		}
		$hitterData = $this->playerData[$attackerSession->getUID()];
		$victimData = $this->playerData[$victimSession->getUID()];
		if($hitterData->invulnerable){
			$attackerSession->tell("You are in invulnerability mode!");
			$event->setCancelled();
			return;
		}
		if($victimData->invulnerable){
			$attackerSession->tell("The vicitm is in invulnerability mode!");
			$event->setCancelled();
			return;
		}
		if(Settings::kitpvp_isSafeArea($victim) or Settings::kitpvp_isSafeArea($damager)){
			$event->setCancelled();
			$attackerSession->tell("You may not attack players at/from spawn!");
		}
		elseif($hitterData->areFriends($victimSession->getUID())){
			$event->setCancelled();
		}
		else{
			/** @noinspection PhpUndefinedFieldInspection */
			$event->_legionpeEta_timestamp = microtime(true);
			/** @noinspection PhpUndefinedFieldInspection */
			$event->_legionpeEta_isLadder = ($victim->getLevel()->getBlockIdAt($victim->getFloorX(), $victim->getFloorY(), $victim->getFloorZ()) === Block::LADDER);
			if($event instanceof EntityDamageByChildEntityEvent){
				$child = $event->getChild();
				if($child instanceof Snowball){
					$event->setKnockBack($event->getKnockBack() * 2.5);
				}
//				elseif($child instanceof Egg){
//					$event->setCancelled();
//					$this->healAround($child);
//				}
				elseif($child instanceof Arrow){
					$points = 0;
					if(!$event->isApplicable(EntityDamageEvent::MODIFIER_ARMOR)){
						$armorValues = [
							Item::LEATHER_CAP => 1,
							Item::LEATHER_TUNIC => 3,
							Item::LEATHER_PANTS => 2,
							Item::LEATHER_BOOTS => 1,
							Item::CHAIN_HELMET => 1,
							Item::CHAIN_CHESTPLATE => 5,
							Item::CHAIN_LEGGINGS => 4,
							Item::CHAIN_BOOTS => 1,
							Item::GOLD_HELMET => 1,
							Item::GOLD_CHESTPLATE => 5,
							Item::GOLD_LEGGINGS => 3,
							Item::GOLD_BOOTS => 1,
							Item::IRON_HELMET => 2,
							Item::IRON_CHESTPLATE => 6,
							Item::IRON_LEGGINGS => 5,
							Item::IRON_BOOTS => 2,
							Item::DIAMOND_HELMET => 3,
							Item::DIAMOND_CHESTPLATE => 8,
							Item::DIAMOND_LEGGINGS => 6,
							Item::DIAMOND_BOOTS => 3,
						];
						foreach($attackerSession->getPlayer()->getInventory()->getArmorContents() as $armor){
							if(isset($armorValues[$armor->getId()])){
								$points += $armorValues[$armor->getId()];
							}
						}
					}
					list(, , , $damage, $fire, $knockPct) = Settings::kitpvp_getBowInfo($hitterData->getBowLevel());
					$event->setDamage(max($damage - $points, 0));
					if($fire > 0){
						$victim->setOnFire($fire / 20);
						$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(
							new CallbackPluginTask($this->getMain(), function() use($victim){
								$victim->setOnFire(0);
							}), $fire);
					}
					if($knockPct > 0){
						$event->setKnockBack($event->getKnockBack() * (1 + $knockPct / 100)); // what the, I put / as *?
					}
				}
			}
		}
	}
	public function e_onRegainHealth(EntityRegainHealthEvent $event){
		$player = $event->getEntity();
		if($player instanceof Player and $event->getRegainReason() === EntityRegainHealthEvent::CAUSE_EATING){
			if($player->getInventory()->getItemInHand()->getId() === Item::CARROT){
				$event->setAmount(3);
			}
		}
	}
	/**
	 * @param PlayerDeathEvent $event
	 * @priority MONITOR
	 * @ignoreCancelled true
	 */
	public function e_onDeath(PlayerDeathEvent $event){
		$victim = $event->getEntity();
		if($victim instanceof Player){
			$session = $this->main->getSessions()->getSession($victim);
			if(!$session->inSession($this)){
				return;
			}
			$this->i_onDead($victim);
			$cause = $victim->getLastDamageCause();
			if($cause instanceof EntityDamageByEntityEvent){
				$killer = $cause->getDamager();
				if($killer instanceof Player){
					$this->i_onKill($killer, $victim);
				}
			}
			elseif($cause->getCause() === EntityDamageEvent::CAUSE_FALL and isset($cause->_legionpeEta_fallCause)){
				/** @var object $fallCause */
				$fallCause = $cause->_legionpeEta_fallCause;
				// $cause: fall event that led to death
				// $fallCause: damageByEntity event that led to fall
				/** @var bool $ladder */
				$ladder = $fallCause->_legionpeEta_isLadder;
				$shoot = $fallCause instanceof EntityDamageByChildEntityEvent and $fallCause->getChild() instanceof Arrow;
				$damager = $fallCause->getDamager();
				$this->i_onKill($damager, $victim, true, $ladder, $shoot);
				unset($cause->_legionpeEta_fallCause);
			}
		}
	}
	/**
	 * @param PlayerDropItemEvent $event
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function e_onPickup(PlayerDropItemEvent $event){
		$ses = $this->getMain()->getSessions()->getSession($player = $event->getPlayer());
		if(!($ses instanceof Session) or !$ses->inSession($this)){
			return;
		}
		$data = $this->getPlayerData($ses);
		if(!($data instanceof PvpSessionData)){
			return;
		}
		$event->setCancelled();
	}
	public function onShootBow(EntityShootBowEvent $event){
		$ent = $event->getEntity();
		if(!($ent instanceof Player)){
			return;
		}
		if(!(($ses = $this->getMain()->getSessions()->getSession($ent)) instanceof Session)){
			return;
		}
		if(!$ses->inSession($this)){
			return;
		}
		if(!(($d = $this->getPlayerData($ses)) instanceof PvpSessionData)){
			return;
		}
		$bow = $d->getBowLevel();
		$hasFire = Settings::kitpvp_getBowInfo($bow)[4] > 0;
		if($hasFire){
			$event->getProjectile()->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_ONFIRE);
		}
	}
	public function onRespawn(PlayerRespawnEvent $event, Session $session){
		if(!($session instanceof Session) or !$session->inSession($this)){
			return;
		}
		$event->setRespawnPosition(Settings::kitpvp_spawn($this->main->getServer()));
		$this->giveKits($session);
	}
	public function onMove(Session $session, PlayerMoveEvent $event){}

	private function i_onDead(Player $player){
		$s = $this->main->getSessions()->getSession($player);
		$uid = $s->getUID();
		$data = $this->playerData[$uid];
		$data->incrementDeaths();
		$data->setStreakCnt(0);
		$s->tell("You died! Your number of deaths: %d", $data->getDeaths());
	}
	private function i_onKill(Player $killer, Player $victim, $isFall = false, $isLadder = false, $isArrow = false){
		$killerSession = $this->main->getSessions()->getSession($killer);
		$killerData = $this->playerData[$killerSession->getUID()];
		$killerData->incrementKills();
		if($isFall){
			$action = $isArrow ? "shot":"knocked";
			if($isLadder){
				$killerSession->tell("%s was $action off a ladder by you and fell to death! Your number of kills: %d", $victim->getDisplayName(), $killerData->getKills());
				$victim->sendMessage("You were $action off a ladder by $killerSession and fell to death!");
			}
			else{
				$killerSession->tell("%s was $action to a deadly fall by you! Your number of kills: %d", $victim->getDisplayName(), $killerData->getKills());
				$victim->sendMessage("You were $action to a deadly fall by $killerSession!");
			}
		}
		else{
			$action = $isArrow ? "shot":"killed";
			$kills = $killerData->getKills();
			$ord = MUtils::num_getOrdinal($kills);
			$killerSession->tell("%s is your $kills$ord kill!", $victim->getDisplayName());
			$victim->sendMessage("You were $action by $killerSession!");
		}
		$factor = Settings::coinsFactor($killerSession);
		$killerSession->setCoins($killerSession->getCoins() + $factor);
		Settings::kitpvp_killHeal($killerSession);
		$killerSession->tell("Your coins +$factor => {$killerSession->getCoins()} coins");
		$killerData->setStreakCnt($streak = $killerData->getStreakCnt() + 1);
		if(($streak % 5) === 0){
			$this->pvpChannel->broadcast(sprintf("%s has a continuous streak of %d kills, totally getting %d kills!", $killerSession->getPlayer()->getDisplayName(), $streak, $killerData->getKills()));
			$more = (4 + $streak / 5) * $factor;
			$killerSession->setCoins($killerSession->getCoins() + $more);
			$killerSession->tell("You got $more extra coins for a killstreak!");
		}
		if(mt_rand(0, 99) === 0){
			$bonus = mt_rand(25, 50);
		}
		elseif(mt_rand(0, 499) === 0){
			$bonus = mt_rand(150, 300);
		}
		elseif(mt_rand(0, 749) === 0){
			$bonus = mt_rand(250, 500);
		}
		if(isset($bonus)){
			$this->getDefaultChatChannel()->broadcast("$killerSession received a bonus of $bonus coins!");
			$killerSession->setCoins($killerSession->getCoins() + $bonus);
		}
	}
	/**
	 * @param int|Session $uid
	 * @return int
	 */
	public function getKills($uid){
		if($uid instanceof Session){
			$uid = $uid->getUID();
		}
		if(isset($this->playerData[$uid])){
			return $this->playerData[$uid]->getKills();
		}
		$res = $this->main->getMySQLi()->query("SELECT kills FROM kitpvp WHERE uid=$uid;", MysqlConnection::ASSOC);
		if(is_array($res)){
			return (int) $res["kills"];
		}
		return 0;
	}
	/**
	 * @param int|Session $uid
	 * @return int
	 */
	public function getDeaths($uid){
		if($uid instanceof Session){
			$uid = $uid->getUID();
		}
		if(isset($this->playerData[$uid])){
			return $this->playerData[$uid]->getDeaths();
		}
		$res = $this->main->getMySQLi()->query("SELECT deaths FROM kitpvp WHERE uid=$uid;", MysqlConnection::ASSOC);
		if(is_array($res)){
			return (int) $res["deaths"];
		}
		return 0;
	}
	/**
	 * @param int|Session $uid
	 * @param int &$kills
	 * @param int &$deaths
	 * @return string
	 */
	public function getKDRatio($uid, &$kills = 0, &$deaths = 0){
		if($uid instanceof Session){
			$uid = $uid->getUID();
		}
		if(isset($this->playerData[$uid])){
			$data = $this->playerData[$uid];
			list($kills, $deaths) = [$data->getKills(), $data->getDeaths()];
		}
		else{
			$res = $this->main->getMySQLi()->query("SELECT kills,deaths FROM kitpvp WHERE uid=$uid;", MysqlConnection::ASSOC);
			if(is_array($res)){
				list($kills, $deaths) = [(int) $res["kills"], (int) $res["deaths"]];
			}
		}
		list($kills, $deaths) = [(int) $kills, (int) $deaths];
		if($kills !== 0 and $deaths !== 0){
			if($deaths === 0){
				return "N/A";
			}
			return (string) ($kills / $deaths);
		}
		return "N/A";
	}
	/**
	 * @param int $kills
	 * @return string
	 */
	public function getTag($kills){
		return Settings::kitpvp_getTag($kills);
	}
	public static function getDefaultMysqlDataArray($uid){
		return [
			"uid" => $uid,
			"kills" => 0,
			"deaths" => 0,
			"kit" => 1,
			"bow" => 0,
			"maxstreak" => 0
		];
	}
	private function cancelTpRequest(Session $from, $send = false){
		$data = $this->playerData[$from->getUID()];
		$target = $data->tpRequestTo;
		if($target === -1){
			if($send){
				$from->tell("You don't have a pending teleport request!");
			}
			return $data;
		}
		if(($to = $this->getMain()->getSessions()->getSessionByUID($target)) instanceof Session and $to->inSession($this)){
			$to->tell("$from's teleport request to you has been cancelled.");
			$from->tell("Your teleport request to $to has been cancelled.");
			return $data;
		}
		$data->tpRequestTo = -1;
		return $data;
	}
	private function onFriendCommand(array $args, Session $session){
		switch(strtolower($args[0])){
			case "add":
				return $this->onAddFriendCommand($args, $session);
			case "rm":
			case "remove":
				return $this->onRemoveFriendCommand($args, $session);
			case "l":
			case "list":
				return $this->onListFriendCommand($session);
			case "i":
			case "inbox":
			case "requests":
				return $this->onFriendInboxCommand($session);
			case "o":
			case "outbox":
			case "pending":
				return $this->onFriendOutboxCommand($session);
		}
		return "";
	}
	private function onAddFriendCommand(array $args, Session $session){
		if(!isset($args[1])){
			return "Usage: /friend add <name> (Accept friend request from <name> or send a friend request to <name>";
		}
		$other = $args[1];
		$db = $this->main->getMySQLi();
		$from = $session->getUID();
		$to = $db->fastSearchPlayerUID($other);
		if(!is_int($to)){
			return "$other has never been on this server!";
		}
		if($from === $to){
			return "You cannot add yourself as friend!";
		}
		if($toLarger = $to > $from){
			$large = $to;
			$small = $from;
		}
		else{
			$large = $from;
			$small = $to;
		}
		$result = $db->query("SELECT * FROM kitpvp_friends WHERE smalluid=$small AND largeuid=$large;", MysqlConnection::ASSOC);
		if(!is_array($result)){
			$result = ["smalluid" => $small, "largeuid" => $large, "type" => self::TYPE_BROKE];
			$null = true; // insert
		}
		else{
			foreach(["smalluid", "largeuid", "type"] as $key){
				$result[$key] = (int) $result[$key];
			}
		}
		switch($result["type"]){
			case self::TYPE_BROKE:
				// send
				$type = $toLarger ? self::TYPE_FROM_SMALL:self::TYPE_FROM_LARGE;
				if(isset($null) and $null === true){
					$db->query("INSERT INTO kitpvp_friends(smalluid,largeuid,type)VALUES(%d,%d,%d);", MysqlConnection::RAW, $small, $large, $type);
				}
				else{
					$db->query("UPDATE kitpvp_friends SET type=%d WHERE smalluid=%d AND largeuid=%d;", $type, $small, $large);
				}
				$otherSession = $this->main->getSessions()->getSessionByUID($to);
				if($otherSession instanceof Session){
					$otherSession->tell("$session sent you a friend request!");
				}
				return "A friend request has been sent to $other!";
			case self::TYPE_FROM_SMALL:
			case self::TYPE_FROM_LARGE:
				if(!$this->checkSessionFriendsCount($from, $session->getRank())){
					$session->tell("You cannot have more than %d friends. Donate to get a larger quota.", Settings::kitpvp_maxFriends($session));
					return "";
				}
				if(!$this->checkSessionFriendsCount($to)){
					$session->tell("$other already has his maximum number of friends!");
					return "";
				}
				if($result["type"] === self::TYPE_FROM_SMALL and $toLarger){
					return "You have already sent a friend request to $other!";
				}
				if($result["type"] === self::TYPE_FROM_LARGE and !$toLarger){
					return "You have already sent a friend request to $other!";
				}
				// accept
				$db->query("UPDATE kitpvp_friends SET type=%d WHERE smalluid=%d AND largeuid=%d;", MysqlConnection::ASSOC, self::TYPE_FRIEND, $small, $large);
				$otherSession = $this->main->getSessions()->getSessionByUID($to);
				$this->playerData[$session->getUID()]->addToFriends($to);
				if($otherSession instanceof Session){
					$otherSession->tell("$session accepted your friend request!");
					if(isset($this->playerData[$to])){
						$this->playerData[$to]->addToFriends($from);
					}
				}
				return "You are now friend with $other!";
			case self::TYPE_FRIEND:
				return "You are already friend with $other!";
		}
		return "";
	}
	private function checkSessionFriendsCount($uid, $rank = false){
		if($rank === false){
			if(($session_ = $this->getMain()->getSessions()->getSessionByUID($uid)) instanceof Session){
				$rank = $session_->getRank();
			}
			else{
				$result = $this->main->getMySQLi()->query("SELECT rank FROM players WHERE uid=$uid;", MysqlConnection::ASSOC);
				if(is_array($result)){
					$rank = (int) $result["rank"];
				}
				else{
					$rank = 0;
				}
			}
		}
		$max = Settings::kitpvp_maxFriends($rank);
		if(isset($this->playerData[$uid])){
			$cnt = $this->playerData[$uid]->countFriends();
		}
		else{
			$result = $this->main->getMySQLi()->query("SELECT COUNT(*)AS cnt FROM kitpvp_friends WHERE (smalluid=$uid OR largeuid=$uid)AND type=%d", MysqlConnection::ASSOC, self::TYPE_FRIEND);
			if(is_array($result)){
				$cnt = (int) $result["cnt"];
			}
			else{
				return false;
			}
		}
		return $cnt < $max;
	}
	private function onRemoveFriendCommand(array $args, Session $session){
		if(!isset($args[1])){
			return "Usage: /friend remove <name>";
		}
		$uid = $session->getUID();
		$otherUid = $this->main->getMySQLi()->fastSearchPlayerUID($args[1]);
		if(!is_int($otherUid)){
			return "Player $args[1] has never been on this server!";
		}
		if($uid === $otherUid){
			return "You can't remove yourself as friend!";
		}
		list($small, $large) = $uid < $otherUid ? [$uid, $otherUid]:[$otherUid, $uid];
		$result = $this->main->getMySQLi()->query("SELECT * FROM kitpvp_friends WHERE smalluid=$small AND largeuid=$large;", MysqlConnection::ASSOC);
		if(!is_array($result) or ((int) $result["type"]) === self::TYPE_BROKE){
			return "$args[1] is not in your friend list/outbox/inbox!";
		}
		$origType = (int) $result["type"];
		$listName = ($origType === self::TYPE_FRIEND) ? "list" : (($origType === self::TYPE_FROM_LARGE and $uid > $otherUid) ? "outbox":"inbox");
		$this->main->getMySQLi()->query("DELETE FROM kitpvp_friends WHERE(smalluid=$small AND largeuid=$large);", MysqlConnection::RAW);
		$this->playerData[$session->getUID()]->rmFromFriends($otherUid);
		if(($otherSession = $this->getMain()->getSessions()->getSessionByUID($otherUid)) instanceof Session){
			if($origType === self::TYPE_FRIEND){
				$otherSession->tell("$session removed you from his/her friend list!");
				if(isset($this->playerData[$otherUid])){
					$this->playerData[$otherUid]->rmFromFriends($uid);
				}
			}
			elseif($origType = self::TYPE_FROM_LARGE and $uid > $otherUid){
				$otherSession->tell("$session cancelled his/her friend request to you!");
			}
			else{
				$otherSession->tell("$session rejected your friend request!");
			}
		}
		return "$args[1] was removed from your friend $listName.";
	}
	private function onListFriendCommand(Session $session){
		$rank = $session->getRank();
		$max = Settings::kitpvp_maxFriends($rank);
		$uid = $session->getUID();
		$query = "SELECT(SELECT primaryname FROM players WHERE uid=IF(kitpvp_friends.smalluid=$uid,kitpvp_friends.largeuid, kitpvp_friends.smalluid))AS primaryname FROM kitpvp_friends WHERE type=%d AND(smalluid=$uid OR largeuid=$uid);";
		$friends = $this->main->getMySQLi()->query($query, MysqlConnection::ALL, self::TYPE_FRIEND);
		$output = sprintf("You have %d/%d friends:\n", count($friends), $max);
		foreach($friends as $friend){
			$online = ($this->main->getServer()->getPlayerExact($friend["primaryname"]) instanceof Player) ? "online":"offline";
			$output .= $friend["primaryname"] . " ($online), ";
		}
		return substr($output, 0, -2);
	}
	private function onFriendInboxCommand(Session $session){
		$uid = $session->getUID();
		$fromSmall = self::TYPE_FROM_SMALL;
		$fromLarge = self::TYPE_FROM_LARGE;
		$friend = self::TYPE_FRIEND;
		$result = $this->main->getMySQLi()->query("SELECT(SELECT primaryname FROM players WHERE uid=sel.uid)AS name,theirFriends AS theirFriendsCnt,(SELECT rank FROM players WHERE uid=sel.uid)AS rank FROM(SELECT uid,(SELECT COUNT(*) FROM kitpvp_friends WHERE type=$friend AND(smalluid=uid OR largeuid=uid))AS theirFriends FROM(SELECT IF(smalluid=$uid,largeuid,smalluid)AS uid FROM kitpvp_friends WHERE(smalluid=$uid AND type=$fromLarge)OR(largeuid=$uid AND type=$fromSmall))AS friends)AS sel", MysqlConnection::ALL);
		$names = [];
		foreach($result as &$row){
			$row["theirFriendsCnt"] = (int) $row["theirFriendsCnt"];
			$name = $row["name"];
			if($row["theirFriendsCnt"] >= Settings::kitpvp_maxFriends((int) $row["rank"])){
				$name = TextFormat::RED . "$name (full)" . TextFormat::WHITE;
			}
			$names[] = $name;
		}
		return sprintf("You have %d received friend request(s) from:\n%s.", count($names), count($names) > 0 ? implode(", ", $names):"---");
	}
	private function onFriendOutboxCommand(Session $session){
		$uid = $session->getUID();
		$result = $this->main->getMySQLi()->query("SELECT(SELECT primaryname FROM players WHERE uid=sel.uid)AS name FROM(SELECT IF(smalluid=$uid,largeuid,smalluid)AS uid FROM kitpvp_friends WHERE(smalluid=$uid AND type=%d)OR(largeuid=$uid AND type=%d))AS sel;", MysqlConnection::ALL, self::TYPE_FROM_SMALL, self::TYPE_FROM_LARGE);
		$names = [];
		foreach($result as $row){
			$names[] = $row["name"];
		}
		return sprintf("You have %d requests in your outbox to:\n%s", count($names), implode(", ", $names));
	}
	public function giveKits(Session $session){
		$this->playerData[$u = $session->getUID()]->getKitInUse()->equip($session->getPlayer()->getInventory(), $this->playerData[$u]);
	}

	public function __toString(){
		return $this->getName();
	}
	/**
	 * @param Session|Player $player
	 * @param bool $revSearch
	 * @return PvpSessionData|null
	 */
	public function getPlayerData($player, $revSearch = false){
		if($player instanceof Session){
			return isset($this->playerData[$player->getUID()]) ? $this->playerData[$player->getUID()]:null;
		}
		elseif($player instanceof Player){
			if($revSearch){
				$keys = array_keys($this->playerData);
				for($i = count($this->playerData); $i > 0;){
					if($this->playerData[$keys[--$i]]->getSession()->getPlayer() === $player){
						return $this->playerData[$keys[$i]];
					}
				}
			}
			else{
				foreach($this->playerData as $data){
					if($data->getSession()->getPlayer() === $player){
						return $data;
					}
				}
			}
		}
		return null;
	}
	/**
	 * @return PvpSessionData[]
	 */
	public function getPlayerDataArray(){
		return $this->playerData;
	}
	/**
	 * @return PvpKitModel[]
	 */
	public function getModels(){
		return $this->chunkListener->getSpawnedModels();
	}
	public function saveSessionsData(){
		foreach($this->playerData as $data){
			$data->update(false);
		}
	}
	public function countPlayers(){
		return count($this->playerData);
	}
	public function onDisable(){
	}
	/**
	 * @return PvpGameChunkListener
	 */
	public function getChunkListener(){
		return $this->chunkListener;
	}
	/**
	 * @param Session $ses
	 * @param int $from
	 * @param int $to
	 * @return bool
	 */
	public function onInventoryStateChange(Session $ses, $from, $to){
		if($from === Session::INV_NORMAL_ACCESS){
			$this->getPlayerData($ses)->invulnerable = true;
		}
		elseif($to === Session::INV_NORMAL_ACCESS){
			$this->getPlayerData($ses)->invulnerable = false;
		}
		return true;
	}
}
