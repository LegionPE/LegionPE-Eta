<?php

namespace legionpe\games\kitpvp;

use legionpe\config\Settings;
use legionpe\utils\CallbackPluginTask;
use pocketmine\event\level\ChunkLoadEvent;
use pocketmine\event\level\ChunkUnloadEvent;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\scheduler\PluginTask;

class PvpGameChunkListener extends PluginTask implements Listener{
	/** @var PvpGame */
	private $game;
	/** @var \pocketmine\level\Location[][] */
	private $modelLocs = [];
	/** @var \pocketmine\level\Location[][] */
	private $shopLocs = [];
	private $bowHash;
	/** @var PvpKitModel[] */
	private $models = [];
	/** @var PvpUpgradeDummyShop[] */
	private $shops = [];
	/** @var null|PvpBowHolder */
	private $bow = null;
	public function __construct(PvpGame $game){
		$this->game = $game;
		$this->game->getMain()->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackPluginTask($game->getMain(), function(){
			foreach($this->shops as $shop){
				$shop->nextType();
			}
		}), 80);
		$bowLoc = Settings::kitpvp_getBowLocation($game->getMain()->getServer());
		$this->bowHash = Level::chunkhash($bowLoc->getFloorX() >> 4, $bowLoc->getFloorZ() >> 4);
	}
	public function addModel(Location $loc, $id){
		$hash = Level::chunkHash($loc->getFloorX() >> 4, $loc->getFloorZ() >> 4);
		if(!isset($this->modelLocs[$hash])){
			$this->modelLocs[$hash] = [];
		}
		$this->modelLocs[$hash][$id] = $loc;
	}
	public function addShop(Location $loc, $col){
		$hash = Level::chunkHash($loc->getFloorX() >> 4, $loc->getFloorZ() >> 4);
		if(!isset($this->shopLocs[$hash])){
			$this->shopLocs[$hash] = [];
		}
		$this->shopLocs[$hash][$col] = $loc;
	}
	public function onLoadChunk(ChunkLoadEvent $event){
		if($event->getLevel()->getName() !== "world_pvp"){
			return;
		}
		$chunk = $event->getChunk();
		$hash = Level::chunkHash($chunk->getX(), $chunk->getZ());
		if(isset($this->modelLocs[$hash])){
			foreach($this->modelLocs[$hash] as $id => $loc){
				$model = new PvpKitModel($this->game, $id, $loc);
				$model->spawnToAll();
				$this->models[$id] = $model;
			}
		}
		if(isset($this->shopLocs[$hash])){
			foreach($this->shopLocs[$hash] as $col => $loc){
				$shop = new PvpUpgradeDummyShop($this->game, $loc, $col);
				$shop->spawnToAll();
				$this->shops[$col] = $shop;
			}
		}
		if($hash === $this->bowHash){
			$this->bow = new PvpBowHolder(Settings::kitpvp_getBowLocation($this->game->getMain()->getServer()), $this->game);
		}
	}
	public function onUnloadChunk(ChunkUnloadEvent $event){
		if($event->getLevel()->getName() !== "world_pvp"){
			return;
		}
		$chunk = $event->getChunk();
		$hash = Level::chunkHash($chunk->getX(), $chunk->getZ());
		if(isset($this->modelLocs[$hash])){
			foreach($this->modelLocs[$hash] as $id => $model){
				if(isset($this->models[$id])){
					$this->models[$id]->_kill();
					unset($this->models[$id]);
				}
			}
		}
		if(isset($this->shopLocs[$hash])){
			foreach($this->shopLocs[$hash] as $col => $loc){
				if(isset($this->shops[$col])){
					$this->shops[$col]->_kill();
					unset($this->shops[$col]);
				}
			}
		}
		if($hash === $this->bowHash){
			$this->bow->_kill();
			$this->bow = null;
		}
	}
	/**
	 * @return PvpKitModel[]
	 */
	public function getSpawnedModels(){
		return $this->models;
	}
	/**
	 * @return PvpUpgradeDummyShop[]
	 */
	public function getSpawnedShops(){
		return $this->shops;
	}
	public function onRun($ticks){
		foreach($this->shops as $shop){
			$shop->nextType();
		}
	}
}
