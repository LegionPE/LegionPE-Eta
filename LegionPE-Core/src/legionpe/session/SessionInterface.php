<?php

namespace legionpe\session;

use legionpe\config\Settings;
use legionpe\LegionPE;
use legionpe\MysqlConnection;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\protocol\LoginPacket;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;

class SessionInterface extends PluginTask implements Listener, SessionEvents{
	private $main;
	/** @var Session[] */
	private $sessions = [];
	/** @var MuteIssue[] */
	public $mutedIps = [];
	public function __construct(LegionPE $main){
		parent::__construct($this->main = $main);
		$this->main->getServer()->getScheduler()->scheduleRepeatingTask($this, 20);
	}
	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk instanceof LoginPacket){
			$pk->username = str_replace([" ", "!", "?", "-", ",", "."], "_", $pk->username);
		}
	}
	public function onDataPacketSend(DataPacketSendEvent $event){
		$pk = $event->getPacket();
		$pk->pid();
	}
	public function onPlayerKick(PlayerKickEvent $event){
		if($event->getReason() === "server full"){
			$rank = $this->getMain()->getMySQLi()->query("SELECT rank FROM players WHERE primaryname=%s;", MysqlConnection::ASSOC, strtolower($event->getPlayer()->getName()));
			if(is_array($rank)){
				$rank = $rank["rank"];
				if(($rank & (Settings::RANK_SECTOR_IMPORTANCE | Settings::RANK_SECTOR_PERMISSION) & (~Settings::RANK_IMPORTANCE_TESTER)) > 0){
					$event->setCancelled();
				}
			}
		}
	}
	/**
	 * @param PlayerPreLoginEvent $login
	 * @priority HIGH
	 * @ignoreCancelled true
	 */
	public function onPlayerPreconnect(PlayerPreLoginEvent $login){
		$name = strtolower($login->getPlayer()->getName());
		if(in_array($name, ["pocketmine", "console", "server", "rcon", "legionpe", "botbot", "fakeclient", "pocketbot"])){
			$login->setCancelled();
			$login->setKickMessage("Bad username");
		}
		else{
			$reason = $this->getMain()->getMySQLi()->query("SELECT msg FROM ipbans WHERE %s LIKE ip AND %d<(unix_timestamp(creation)+length);", MysqlConnection::ASSOC, $login->getPlayer()->getAddress(), time());
			if(is_array($reason)){
				$login->setCancelled();
				$login->setKickMessage("You are IP-banned! Reason: " . (isset($reason["msg"]) ? $reason["msg"]:"no reason specified :("));
			}
			if(($old = $this->getMain()->getServer()->getPlayerExact($name)) instanceof Player){ // if has name collision
				if($old->getAddress() !== ($ip = $login->getPlayer()->getAddress())){ // if IP is different
					if(substr($ip, 0, 8) !== "192.168." and $ip !== "119.247.51.252"){ // if new IP isn't local and isn't PEMapModder's IP address (yes I am making it for myself)
						$login->setCancelled();
						$login->setKickMessage("Player already online with differnet IP");
					}
				}
			}
		}
	}
	/**
	 * @param PlayerLoginEvent $event
	 * @priority HIGH
	 */
	public function onPlayerConnect(PlayerLoginEvent $event){
		$this->sessions[Session::offset($event->getPlayer())] = new Session($this, $event);
	}
	public function onPlayerJoin(PlayerJoinEvent $event){
		if(isset($this->sessions[$offset = Session::offset($event->getPlayer())])){
			$this->sessions[$offset]->join($event);
			$this->sessions[$offset]->activity();
		}
	}
	/**
	 * @param PlayerQuitEvent $event
	 * @priority HIGH
	 */
	public function onPlayerDisconnect(PlayerQuitEvent $event){
		$offset = Session::offset($event->getPlayer());
		if(isset($this->sessions[$offset])){
			$this->sessions[$offset]->finalize($event);
			unset($this->sessions[$offset]);
		}
	}

	/**
	 * @param PlayerCommandPreprocessEvent $event
	 * @priority HIGH
	 */
	public function h_onPreCmd(PlayerCommandPreprocessEvent $event){
		$player = $event->getPlayer();
//		$this->getMain()->getLogger()->info("Command preprocess for " . $player->getDisplayName());
		$this->sessions[Session::offset($player)]->h_onPreCmd($event);
	}
	/**
	 * @param EntityDamageEvent $event
	 * @priority HIGH
	 */
	public function h_onDamage(EntityDamageEvent $event){
		$player = $event->getEntity();
		if($player instanceof Player){
			$this->sessions[Session::offset($player)]->h_onDamage($event);
		}
	}
	/**
	 * @param PlayerItemConsumeEvent $event
	 * @priority HIGH
	 */
	public function h_onItemConsume(PlayerItemConsumeEvent $event){
		$this->sessions[$offset = Session::offset($event->getPlayer())]->h_onItemConsume($event); // oic => on item consume :P
		$this->sessions[$offset]->activity();
	}
	/**
	 * @param PlayerDropItemEvent $event
	 * @priority HIGH
	 */
	public function h_onDropItem(PlayerDropItemEvent $event){
		$this->sessions[$offset = Session::offset($event->getPlayer())]->h_onDropItem($event);
		$this->sessions[$offset]->activity();
	}
	/**
	 * @param PlayerInteractEvent $event
	 * @priority HIGH
	 */
	public function h_onInteract(PlayerInteractEvent $event){
		$this->sessions[$offset = Session::offset($event->getPlayer())]->h_onInteract($event);
		$this->sessions[$offset]->activity();
	}
	public function h_onDeath(PlayerDeathEvent $event){
		$event->setDrops([]);
		$event->setDeathMessage("");
		$event->setKeepInventory(false);
	}
	/**
	 * @param PlayerRespawnEvent $event
	 * @priority HIGH
	 */
	public function h_onRespawn(PlayerRespawnEvent $event){
		$this->sessions[$offset = Session::offset($event->getPlayer())]->h_onRespawn($event);
		$this->sessions[$offset]->activity();
	}
	/**
	 * @param PlayerMoveEvent $event
	 * @priority HIGH
	 */
	public function h_onMove(PlayerMoveEvent $event){
		$player = $event->getPlayer();
		$this->sessions[$offset = Session::offset($player)]->h_onMove($event);
		$this->sessions[$offset]->activity();
	}
	/**
	 * @param BlockBreakEvent $event
	 * @priority HIGH
	 */
	public function h_onBreak(BlockBreakEvent $event){
		$this->sessions[Session::offset($event->getPlayer())]->h_onBreak($event);
	}
	/**
	 * @param BlockPlaceEvent $event
	 * @priority HIGH
	 */
	public function h_onPlace(BlockPlaceEvent $event){
		$this->sessions[Session::offset($event->getPlayer())]->h_onPlace($event);
	}
	/**
	 * @param InventoryOpenEvent $event
	 * @priority HIGH
	 */
	public function h_onOpenInv(InventoryOpenEvent $event){
		if(isset($this->sessions[$offset = Session::offset($event->getPlayer())])){
			$this->sessions[$offset = Session::offset($event->getPlayer())]->h_onOpenInv($event);
			$this->sessions[$offset]->activity();
		}
	}
	/**
	 * @param InventoryPickupItemEvent $event
	 * @priority HIGH
	 */
	public function h_onPickup(InventoryPickupItemEvent $event){
		$player = $event->getInventory()->getHolder();
		if($player instanceof Player){
			$this->sessions[Session::offset($player)]->h_onPickup($event);
		}
	}
	/**
	 * @param PlayerChatEvent $event
	 * @priority HIGH
	 */
	public function h_onChat(PlayerChatEvent $event){
		$this->sessions[$offset = Session::offset($event->getPlayer())]->h_onChat($event);
		$this->sessions[$offset]->activity();
	}
	/**
	 * @param PlayerItemHeldEvent $event
	 * @priority HIGH
	 */
	public function h_onItemHeld(PlayerItemHeldEvent $event){
		$player = $event->getPlayer();
		if(isset($this->sessions[$o = Session::offset($player)])){
			$this->sessions[$o]->h_onItemHeld($event);
		}
	}
	/**
	 * @param BlockPlaceEvent $event
	 * @priority LOWEST
	 */
	public function l_onBlockPlace(BlockPlaceEvent $event){
		$player = $event->getPlayer();
		$this->sessions[$offset = Session::offset($player)]->l_onBlockPlace($event);
		$this->sessions[$offset]->activity();
	}
	/**
	 * @param BlockBreakEvent $event
	 * @priority LOWEST
	 */
	public function l_onBlockBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		$this->sessions[$offset = Session::offset($player)]->l_onBlockBreak($event);
		$this->sessions[$offset]->activity();
	}
	/**
	 * @param EntityDamageEvent $event
	 * @priority LOWEST
	 */
	public function l_onDamage(EntityDamageEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$this->getSession($entity)->l_onDamage($event);
		}
	}
	/**
	 * @param PlayerMoveEvent $event
	 * @priority MONITOR
	 */
	public function mon_onMove(PlayerMoveEvent $event){
		$entity = $event->getPlayer();
		if($entity instanceof Player){
			if(isset($this->sessions[Session::offset($entity)])){
				$this->sessions[Session::offset($entity)]->mon_onMove($event);
			}
		}
	}
	/**
	 * @param EntityTeleportEvent $event
	 * @priority MONITOR
	 */
	public function mon_onTeleport(EntityTeleportEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player and isset($this->sessions[Session::offset($entity)])){
			$this->sessions[Session::offset($entity)]->mon_onTeleport($event);
		}
	}
	/**
	 * @param PlayerInteractEvent $event
	 * @priority HIGH
	 */
	public function hst_onInteract(PlayerInteractEvent $event){
		$this->sessions[Session::offset($event->getPlayer())]->hst_onInteract($event);
	}

	/**
	 * @return LegionPE
	 */
	public function getMain(){
		return $this->main;
	}
	/**
	 * @return Session[]
	 */
	public function getAll(){
		return array_values($this->sessions);
	}
	/**
	 * @param string|Player $player
	 * @return false|Session
	 */
	public function getSession($player){
		if(!($player instanceof Player)){
			$player = $this->getMain()->getServer()->getPlayer($player);
		}
		if(!($player instanceof Player)){
			return false;
		}
		return isset($this->sessions[$i = Session::offset($player)]) ? $this->sessions[$i]:false;
	}
	public function getSessionByUID($uid){
		foreach($this->sessions as $session){
			if($session->getUID() === $uid){
				return $session;
			}
		}
		return null;
	}
	public function onRun($currentTick){
		foreach($this->sessions as $session){
			$session->onRun($currentTick);
		}
		if(!IS_TEST){
			if(($currentTick % (20 * 5)) < 20){
				foreach($this->sessions as $ses){
					$ses->showToAll();
					$ses->getPlayer()->sendTip(TextFormat::GRAY . "Resent all players you can see");
				}
				$this->mysqlCheck();
			}
		}
	}
	public function mysqlCheck(){
		$rows = $this->getMain()->getMySQLi()->query("SELECT * FROM delete_dat WHERE state=0", MysqlConnection::ALL);
		$done = [3 => [], 1 => [], 2 => []]; // 1: file still exists; 2: success; 3: file not found
		foreach($rows as $row){
			$path = "players/" . $row["fn"] . ".dat";
			if(is_file($path)){
				unlink($path);
				$done[is_file($path) ? 1:2][] = $row["pk"];
			}
			else{
				$done[3][] = $row["pk"];
			}
		}
//		$this->getMain()->getLogger()->info(sprintf("%d player dat delete request(s) processed; %d success, %d unable to delete file, %d file did not exist", count($rows), count($done[2]), count($done[1]), count($done[3])));
		foreach($done as $state => $pka){
			if(count($pka) > 0){
				$query = "UPDATE delete_dat SET state=$state WHERE " . implode(" OR ", array_map(function($pk){
						return "pk=$pk";
					}, $pka));
				$this->getMain()->getMySQLi()->query($query, MysqlConnection::RAW);
			}
		}
	}
}
