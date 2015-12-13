<?php

namespace legionpe\games\spleef;

use legionpe\chat\Channel;
use legionpe\config\Settings;
use legionpe\games\Game;
use legionpe\LegionPE;
use legionpe\MysqlConnection;
use legionpe\session\Session;
use legionpe\utils\CallbackPluginTask;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\entity\Entity;
use pocketmine\entity\Snowball;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\GoldShovel;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Short;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class SpleefGame extends PluginTask implements Game{
	/** @var LegionPE */
	private $main;
	/** @var \legionpe\chat\Channel */
	private $spleefChan;
	/** @var SpleefArena[] */
	private $arenas = [];
	/** @var SpleefSessionData[] */
	private $playerData = [];
	private $spleefWorld;
	public function __construct(LegionPE $main){
		parent::__construct($main);
		$this->main = $main;
		$this->spleefChan = $this->getMain()->getChannelManager()->joinChannel($main, "spleef", Channel::CLASS_MODULAR);
		$this->getMain()->getServer()->getPluginManager()->registerEvents($this, $main);
		$this->getMain()->getServer()->getScheduler()->scheduleDelayedRepeatingTask($this, 1, 1);
		for($id = 1; $id <= Settings::spleef_arenaCnt(); $id++){
			$this->arenas[$id] = new SpleefArena($this, $id);
			$this->arenas[$id]->reset();
		}
		$this->spleefWorld = $this->getMain()->getServer()->getLevelByName("world_spleef");
		$this->getMain()->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackPluginTask($this->getMain(), function(){
			for($id = 1; $id <= Settings::spleef_arenaCnt(); $id++){
				Settings::spleef_updateArenaSigns($this->arenas[$id]);
			}
		}), 40);
	}
	public function getMain(){
		return $this->main;
	}
	public function getName(){
		return "Spleef";
	}
	public function getSelectionItem(){
		return new GoldShovel;
	}
	/**
	 * @param Session $session
	 * @return bool allow join
	 */
	public function onJoin(Session $session){
		$this->playerData[$session->getUID()] = new SpleefSessionData($this, $session);
		$session->tell("%s, welcome to Spleef.", $session->getPlayer()->getName());
		$session->teleport($spawn = Settings::spleef_spawn($this->getMain()->getServer()));
		$session->getPlayer()->setSpawn($spawn);
		if($session->getPlayer()->getGamemode() === 2){
			$session->getPlayer()->setGamemode(0);
		}
		return true;
	}
	/**
	 * @param Session $session
	 * @param bool $force
	 * @return bool allow quit or $force
	 */
	public function onQuit(Session $session, $force){
		$data = $this->getPlayerData($session);
		if(($arena = $data->getArena()) instanceof SpleefArena){
			if($data->isPlaying()){
				$session->tell("You are considered lost since you quitted spleef.");
				$data->addLoss();
			}
			$arena->kick($data, "quitting spleef");
		}
		$data->update();
		unset($this->playerData[$session->getUID()]);
		return true;
	}
	public function getSessionId(){
		return Session::SESSION_GAME_SPLEEF;
	}
	public function getDefaultChatChannel(){
		return $this->spleefChan;
	}
	public function getDefaultCommands(){
		return [
			[
				"name" => "slist",
				"description" => "Spleef: show spleef arena statistics",
				"usage" => "/slist"
			]
		];
	}
	/**
	 * This method will only get called when $session {@link Game::onJoin()}'ed the game
	 * and hasn't {@link Game#onQuit()}'ed yet.<br>
	 * Gracefully throw a {@link \RuntimeException} if it is
	 * @param Session $session
	 * @param string $msg
	 * @param bool $isACTION
	 * @return string
	 */
	public function formatChat(Session $session, $msg, $isACTION){
		$tag = "";
		if(isset($this->playerData[$uid = $session->getUID()]) and ($wins = $this->playerData[$uid]->getWins()) > 0){
			$tag = "[§c" . $wins . "§7]";
		}
		$color = ($session->getRank() & (Settings::RANK_SECTOR_IMPORTANCE | Settings::RANK_SECTOR_PERMISSION) & ~Settings::RANK_IMPORTANCE_TESTER) ? "§f":"";
		return $isACTION ? "* $tag$session $color$msg":"$tag$session: $color$msg";
	}
	/**
	 * @param Session $session
	 * @param string[] $args
	 * @return string
	 */
	public function onStats(Session $session, array $args){
		if(isset($args[0]) and $args[0] === "top"){
			$this->saveSessionsData();
			$cols = 5;
			if(isset($args[1]) and is_numeric($args[1])){
				$cols = (int) $args[1];
			}
			if($cols > 30){
				return "Can't fetch more than 30 rows.";
			}
			$result = $this->getMain()->getMySQLi()->query("SELECT(SELECT names FROM players where uid=spleef.uid)AS names,wins,losses,draws FROM spleef ORDER BY wins DESC LIMIT $cols", MysqlConnection::ALL);
			$output = "";
			$i = 1;
			foreach($result as $row){
				$output .= "#" . ($i++) . ") " . substr($row["names"], 0, -1) . ": " . $row["wins"] . " wins\n";
			}
			return $output;
		}
		$data = $this->getPlayerData($session);
		return "Wins: {$data->getWins()}\nLosses: {$data->getLosses()}\nDraws: {$data->getDraws()}";
	}
	public function saveSessionsData(){
		foreach($this->playerData as $data){
			$data->update();
		}
	}
	public function testCommandPermission(Command $cmd, Session $session){
		if($cmd->getName() === "slist"){
			return true;
		}
		return false;
	}
	public function onCommand(Command $cmd, array $args, Session $session){
		if($cmd->getName() === "slist"){
			if(!isset($args[0])){
				$args[0] = "a";
			}
			if($args[0] === "a"){
				$output = "";
				foreach($this->arenas as $arena){
					$output .= "$arena: ";
					$output .= $arena->isPlaying() ? "playing":"waiting";
					$output .= ". Players: ";
					$closure = function(SpleefSessionData $data){
						return $data->getSession()->getPlayer()->getName();
					};
					$output .= implode(", ", array_map($closure, $arena->getPlayers()));
					$output .= ". Spectators: ";
					$output .= implode(", ", array_map($closure, $arena->getSpectators()));
					$output .= "\n";
				}
				$session->tell($output);
				return;
			}
			if($args[0] === "p"){
				$output = "";
				foreach($this->playerData as $data){
					$arena = $data->getArena();
					$action = $data->isSpectating() ? "spectating":"playing";
					$output .= "\n$data is $action in " . ($arena === null ? "null":$arena);
				}
				$session->tell($output);
				return;
			}
		}
	}
	public function onMove(Session $session, PlayerMoveEvent $event){
		/* nothing to do here */
	}
	public function onRespawn(PlayerRespawnEvent $event, Session $session){
		$data = $this->getPlayerData($session);
		if($data->isInArena()){
			$this->rebouncePlayer($data);
		}
	}
	public function onInventoryStateChange(Session $ses, $from, $to){
		$data = $this->getPlayerData($ses);
		if($data->isPlaying() and $to !== Session::INV_NORMAL_ACCESS){
			$ses->tell("You cannot change your inventory type when you are playing in a match.");
			return false;
		}
		return true;
	}
	public function rebouncePlayer(SpleefSessionData $data){
		if($data->isInArena()){
			if($data->isSpectating()){
				$data->getSession()->teleport($data->getArena()->getConfig()->spectatorSpawnLoc);
			}
			else{
				$arena = $data->getArena();
				$arena->kick($data, "you fell out of the arena");
				$arena->broadcast("$data has fallen!");
				$data->addLoss();
			}
		}
		else{
			$data->getSession()->teleport(Settings::spleef_spawn($this->getMain()->getServer()));
		}
	}
	/**
	 * @param PlayerInteractEvent $event
	 * @priority HIGHEST
	 */
	public function onTouchBlock(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$session = $this->getMain()->getSessions()->getSession($player);
		if(!($session instanceof Session) or !$session->inSession($this)){
			return;
		}
		$event->setCancelled($event->getFace() !== 0xFF);
		if($event->getFace() === 0xFF){
			return;
		}
		Settings::spleef_getType($event->getBlock(), $arenaId, $spectator);
		if($arenaId !== -1){
			$data = $this->getPlayerData($session);
			if($data->isInArena()){
				$this->rebouncePlayer($data);
				return;
			}
			$arena = $this->getArena($arenaId);
			if($spectator === 2){
				$arena->kick($data, "Spectator quit", false);
			}
			elseif($spectator === 1){
				$arena->spectate($this->getPlayerData($session));
			}
			else{
				if($arena->isPlaying()){
					$session->tell("A match is going on in $arena!");
					return;
				}
				if($arena->isFull()){
					$session->tell("The arena is already full!");
					return;
				}
				$arena->join($data);
			}
		}
		else{
			$data = $this->getPlayerData($session);
			if($data->isPlaying() and $data->getArena()->isPlaying() and Settings::spleef_isArenaFloor($event->getBlock())){
				$event->setCancelled(false);
			}
			elseif($result = Settings::spleef_incineratorInfo($event->getBlock())){
				if($event->getItem()->getId() !== Item::AIR){
					$event->setCancelled();
					$item = $event->getItem();
					$player->getInventory()->setItemInHand(Item::get(Item::AIR, 0, 1));
					$player->getInventory()->sendContents($player->getInventory()->getViewers());
					for($x = 936; $x <= 938; $x++){
						$this->spleefWorld->setBlock(new Vector3($x, 21, -13), Block::get(Block::JACK_O_LANTERN), false, false);
					}
					for($z = -16; $z <= -14; $z++){
						$this->spleefWorld->setBlock(new Vector3(935, 21, $z), Block::get(Block::JACK_O_LANTERN, 1), false, false);
					}
					$motion = $result[0]->subtract($player)->multiply(0.3);
					$source = $player->add(0, 1.3);
					$itemEntity = Entity::createEntity("Item", $player->getLevel()->getChunk($source->getX() >> 4, $source->getZ() >> 4), new Compound("", [
						"Pos" => new Enum("Pos", [
							new Double("", $source->getX()),
							new Double("", $source->getY()),
							new Double("", $source->getZ())
						]),

						"Motion" => new Enum("Motion", [
							new Double("", $motion->x),
							new Double("", $motion->y),
							new Double("", $motion->z)
						]),
						"Rotation" => new Enum("Rotation", [
							new Float("", lcg_value() * 360),
							new Float("", 0)
						]),
						"Health" => new Short("Health", 1),
						"Item" => new Compound("Item", [
							"id" => new Short("id", $item->getId()),
							"Damage" => new Short("Damage", $item->getDamage()),
							"Count" => new Byte("Count", $item->getCount())
						]),
						"PickupDelay" => new Short("PickupDelay", 0x7FFF)
					]));
					$itemEntity->spawnToAll();
					$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(new CallbackPluginTask($this->getMain(), function(\pocketmine\entity\Item $item, Vector3 $pos){
						$item->teleport($pos);
					}, $itemEntity, $result[0]), 10);
					$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(new CallbackPluginTask($this->getMain(), function(\pocketmine\entity\Item $item, Vector3 $target){
						$item->setMotion($target->subtract($item)->multiply(0.1));
					}, $itemEntity, $result[1]), 40);
					$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(new CallbackPluginTask($this->getMain(), function(\pocketmine\entity\Item $item){
						$item->kill();
					}, $itemEntity), 70);
					$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(new CallbackPluginTask($this->getMain(), function(){
						for($x = 936; $x <= 938; $x++){
							$this->spleefWorld->setBlock(new Vector3($x, 21, -13), Block::get(Block::PUMPKIN), false, false);
						}
						for($z = -16; $z <= -14; $z++){
							$this->spleefWorld->setBlock(new Vector3(935, 21, $z), Block::get(Block::PUMPKIN, 1), false, false);
						}
					}), 80);
				}
			}
		}
	}
	/**
	 * @param BlockBreakEvent $event
	 * @priority HIGH
	 */
	public function onBlockBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		$session = $this->getMain()->getSessions()->getSession($player);
		if(!$session->inSession($this)){
			return;
		}
		$data = $this->getPlayerData($session);
		if($data->isPlaying() and $data->getArena()->isPlaying() and Settings::spleef_isArenaFloor($event->getBlock())){
			$event->setCancelled(false);
		}
	}
	public function onDamage(EntityDamageEvent $event){
		$damaged = $event->getEntity();
		if(!($damaged instanceof Player) or !(($session = $this->getMain()->getSessions()->getSession($damaged)) instanceof Session) or !$session->inSession($this)){
			return;
		}
		if($event instanceof EntityDamageByChildEntityEvent){
			$entity = $event->getDamager();
			if($entity instanceof Player){
				$ses = $this->main->getSessions()->getSession($entity);
				if($ses instanceof Session and $ses->inSession($this)){
					$data = $this->getPlayerData($ses);
					if($data->isPlaying() and $data->getArena()->isPlaying()){
						$projectile = $event->getChild();
						if($projectile instanceof Snowball){
							$event->setCancelled(false);
							$event->setKnockBack($event->getKnockBack() * 2);
						}
					}
				}
			}
		}
	}
	public function onPickup(InventoryPickupItemEvent $event){
		$inv = $event->getInventory();
		if(!($inv instanceof PlayerInventory)){
			return;
		}
		$player = $inv->getHolder();
		if(!($player instanceof Player)){
			return;
		}
		$session = $this->getMain()->getSessions()->getSession($player);
		if(!$session->inSession($this)){
			return;
		}
		$item = $event->getItem();
		if($item instanceof ItemBlock){
			if($item->getBlock() === Block::SNOW_BLOCK){
				$event->setCancelled();
			}
		}
	}
	public function __toString(){
		return $this->getName();
	}
	public function onDisable(){
		/* nothing to do here */
	}

	public function onRun($tick){
		foreach($this->arenas as $arena){
			$arena->tick();
		}
	}

	public function getArena($id){
		return isset($this->arenas[$id]) ? $this->arenas[$id] : null;
	}
	public function getPlayerData(Session $session){
		return isset($this->playerData[$session->getUID()]) ? $this->playerData[$session->getUID()]:null;
	}
	public function countPlayers(){
		return count($this->playerData);
	}
}
