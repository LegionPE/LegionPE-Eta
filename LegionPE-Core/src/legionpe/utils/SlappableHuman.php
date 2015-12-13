<?php

namespace legionpe\utils;

use legionpe\LegionPE;
use legionpe\session\Session;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\level\format\FullChunk;
use pocketmine\level\Location;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Short;
use pocketmine\Player;

abstract class SlappableHuman extends Human{
	/** @var LegionPE */
	private $main;
	/** @var int[][][] */
	private $dataFlagsArray = [];
	public function __construct(LegionPE $main, Location $vectors, $displayName){
		$this->main = $main;
		$nbt = new Compound;
		$nbt->Pos = new Enum("Pos", [
			new Double(0, $vectors->x),
			new Double(1, $vectors->y),
			new Double(2, $vectors->z)
		]);
		$nbt->Motion = new Enum("Motion", [
			new Double(0, 0),
			new Double(1, 0),
			new Double(2, 0)
		]);
		$nbt->Rotation = new Enum("Rotation", [
			new Float(0, $vectors->yaw),
			new Float(1, $vectors->pitch)
		]);
		$nbt->FallDistance = new Float("FallDistance", 0);
		$nbt->Fire = new Short("Fire", 0);
		$nbt->Air = new Short("Air", 0);
		$nbt->OnGround = new Byte("OnGround", 1);
		$nbt->Invulnerable = new Byte("Invulnerable", 1);
		$nbt->Health = new Short("Health", 20);
		$nbt->NameTag = $displayName;
		$nbt->Inventory = new Enum("Inventory", [new Compound(false, [
			new Short("id", 0),
			new Short("Damage", 0),
			new Byte("Count", 0),
			new Byte("Slot", 9),
			new Byte("TrueSlot", 9)
		])]);
		$X = ($vectors->getFloorX()) >> 4;
		$Z = ($vectors->getFloorZ() >> 4);
		if(!$vectors->getLevel()->isChunkLoaded($X, $Z)){
			$vectors->getLevel()->loadChunk($X, $Z);
		}
		$chunk = $vectors->getLevel()->getChunk($X, $Z);
		$this->inventory = new PlayerInventory($this);
		$this->initSkin();
		parent::__construct($chunk, $nbt);
		$this->nameTag = $displayName;
		$this->teleport($this, $this->yaw, $this->pitch);
	}
	protected function initSkin(){
		$this->setSkin(str_repeat("\x80", 64 * 64 * 4));
	}
	public function attack($damage, EntityDamageEvent $source){
		if(!($source instanceof EntityDamageByEntityEvent)){
			return;
		}
		$damager = $source->getDamager();
		if(!($damager instanceof Player)){
			return;
		}
		$session = $this->main->getSessions()->getSession($damager);
		if($session instanceof Session){
			$this->onSlap($session);
		}
	}
	protected abstract function onSlap(Session $session);
	/**
	 * @return LegionPE
	 */
	public function getMain(){
		return $this->main;
	}
	public function _kill(){
		$this->getLevel()->removeEntity($this);
	}
	public function badConstruct($chunk){
		if($chunk instanceof FullChunk){
			$this->inventory = new PlayerInventory($this);
			$this->server = $chunk->getProvider()->getLevel()->getServer();
			$this->setLevel($chunk->getProvider()->getLevel());
			$chunk->getProvider()->getLevel()->removeEntity($this);
		}
		else{
			echo "What? SlappableHuman::badConstruct() receives parameter 1 as " . is_object($chunk) ? get_class($chunk):gettype($chunk);
		}
	}
	public function spawnTo(Player $player){
		parent::spawnTo($player);
		$this->dataFlagsArray[$player->getId()] = $this->dataProperties;
	}
	public function despawnFrom(Player $player){
		parent::despawnFrom($player);
		if(isset($this->dataFlagsArray[$player->getId()])){
			unset($this->dataFlagsArray[$player->getId()]);
		}
	}

	public function setDataPropertyTo(Player $player, $id, $type, $value){
		if($this->getDataPropertyTo($player, $id) !== $value){
			$this->dataFlagsArray[$player->getId()][$id] = [$type, $value];
			$this->sendData([$player], [$id => $this->dataFlagsArray[$player->getId()][$id]]);
		}
	}
	public function getDataPropertyTo(Player $player, $id){
		if(isset($this->dataFlagsArray[$player->getId()])){
			return isset($this->dataFlagsArray[$player->getId()][$id]) ? $this->dataFlagsArray[$player->getId()][$id][1] : null;
		}
		return null;
	}
	public function getDataPropertyTypeTo(Player $player, $id){
		if(isset($this->dataFlagsArray[$player->getId()])){
			return isset($this->dataFlagsArray[$player->getId()][$id]) ? $this->dataFlagsArray[$player->getId()][$id][0] : null;
		}
		return null;
	}
	public function setDataFlagTo(Player $player, $propertyId, $id, $value = true, $type = self::DATA_TYPE_BYTE){
		if($this->getDataFlagTo($player, $propertyId, $id) !== $value){
			$flags = (int) $this->getDataPropertyTo($player, $propertyId);
			$flags ^= 1 << $id;
			$this->setDataPropertyTo($player, $propertyId, $type, $flags);
		}
	}
	public function getDataFlagTo(Player $player, $propertyId, $id){
		return (((int) $this->getDataPropertyTo($player, $propertyId)) & (1 << $id)) > 0;
	}
	public function setNameTagTo(Player $player, $nameTag){
//		$this->despawnFrom($player);
		$this->setNameTag($nameTag);
//		$this->spawnTo($player);
	}
}
