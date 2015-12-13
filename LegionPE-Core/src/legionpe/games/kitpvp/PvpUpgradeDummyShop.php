<?php

namespace legionpe\games\kitpvp;

use legionpe\config\Settings;
use legionpe\session\Session;
use legionpe\utils\SlappableHuman;
use pocketmine\entity\Human;
use pocketmine\level\Location;

class PvpUpgradeDummyShop extends SlappableHuman{
	const NETWORK_ID = Human::NETWORK_ID;
	/** @var PvpGame */
	private $game;
	/** @var string */
	private $column;
	/** @var int */
	private $max;

	private $curPos = 0;

	/**
	 * @param PvpGame $game
	 * @param Location $loc
	 * @param string $columnName
	 */
	public function __construct($game, $loc, $columnName = ""){
		if(!($game instanceof PvpGame)){
			$this->badConstruct($game);
			return;
		}
		$this->game = $game;
		$this->column = $columnName;
		$this->max = Settings::kitpvp_maxLevel($this->column);
		parent::__construct($game->getMain(), $loc, "Upgrade $columnName");
	}
	public function onSlap(Session $session){
		$data = $this->game->getPlayerData($session);
		if($data === null){
			return;
		}
		if($this->column === Settings::KIT_ARROWS and $data->getBowLevel() === 0){
			$session->tell("You don't have a bow. Why are you buying arrows? Upgrade your bows first.");
			return;
		}
		$kit = $data->getEditingKit();
		$curLevel = $kit->{$this->column};
		if($curLevel === $this->max){
			$session->tell("You already have the maximum level of $this->column!");
			return;
		}
		$buy = $data->isGoingToBuy;
		$oldInfo = Settings::kitpvp_getKitUpgradeInfo($this->column, $curLevel);
		$newInfo = Settings::kitpvp_getKitUpgradeInfo($this->column, $curLevel + 1);
		if(!is_array($buy) or $buy["column"] !== $this->column or microtime(true) - $buy["timestamp"] > 5){
			$price = $newInfo->getPrice();
			$balance = $session->getCoins();
			if($balance < $price){
				$session->tell("You need at least %g more coins to buy this kit!", $price - $balance);
				$data->isGoingToBuy = null;
				return;
			}
			if($newInfo->sendCantPurchaseMessage($session)){
				$data->isGoingToBuy = null;
				return;
			}
			$data->isGoingToBuy = [
				"column" => $this->column,
				"timestamp" => microtime(true)
			];
			$session->tell("Upgrade $this->column to level %d (%g coins required)", $curLevel + 1, $newInfo->getPrice());
			$oldName = $oldInfo->itemsToString();
			$session->tell("You will be equipped with %s%s after you purchase this upgrade.", $newInfo->itemsToString(), $oldName === false ? "":" instead of $oldName");
			$session->tell("Click me again within 5 seconds to purchase this upgrade.");
		}
		else{
			$data->isGoingToBuy = null;
			if($newInfo->sendCantPurchaseMessage($session)){
				return;
			}
			$price = $newInfo->getPrice();
			$balance = $session->getCoins();
			if($balance < $price){
				$session->tell("You need %g more coins to buy this kit!", $price - $balance);
				return;
			}
			$session->setCoins($balance - $price);
			$kit->{$this->column} = $curLevel + 1;
			$kit->updated = true;
			$models = $this->game->getModels();
			if(isset($models[$kit->kitid])){
				$model = $models[$kit->kitid];
				$model->despawnFrom($session->getPlayer());
				$model->spawnTo($session->getPlayer());
			}
			$session->tell("Your $this->column has been upgraded to level %d!", $curLevel + 1);
			$session->tell("Rejoin KitPvP or suicide to activate the kit change!");
		}
	}
	public function nextType(){
		/** @var \legionpe\config\KitUpgradeInfo[] $items */
		$items = Settings::$KITPVP_KITS[$this->column];
		switch($this->column){
			case Settings::KIT_HELMET:
				$this->getInventory()->setHelmet($items[$this->curPos++]->getItem());
				$players = $this->game->getMain()->getServer()->getLevelByName("world_pvp")->getPlayers();
				$this->getInventory()->sendArmorContents($players);
				break;
			case Settings::KIT_CHESTPLATE:
				$this->getInventory()->setChestplate($items[$this->curPos++]->getItem());
				$players = $this->game->getMain()->getServer()->getLevelByName("world_pvp")->getPlayers();
				$this->getInventory()->sendArmorContents($players);
				break;
			case Settings::KIT_LEGGINGS:
				$this->getInventory()->setLeggings($items[$this->curPos++]->getItem());
				$players = $this->game->getMain()->getServer()->getLevelByName("world_pvp")->getPlayers();
				$this->getInventory()->sendArmorContents($players);
				break;
			case Settings::KIT_BOOTS:
				$this->getInventory()->setBoots($items[$this->curPos++]->getItem());
				$players = $this->game->getMain()->getServer()->getLevelByName("world_pvp")->getPlayers();
				$this->getInventory()->sendArmorContents($players);
				break;
			default:
				$item = $items[$this->curPos++]->getItem();
				$this->getInventory()->setItemInHand($item);
				$this->getInventory()->sendHeldItem($this->getInventory()->getViewers());
				break;
		}
		if(!isset($items[$this->curPos])){
			$this->curPos = 0;
		}
	}
	public function __toString(){
		return parent::__toString() . "#$this->column";
	}
}
