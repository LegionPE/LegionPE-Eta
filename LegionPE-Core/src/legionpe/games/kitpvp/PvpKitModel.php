<?php

namespace legionpe\games\kitpvp;

use legionpe\config\Settings;
use legionpe\session\Session;
use legionpe\utils\SlappableHuman;
use pocketmine\entity\Human;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

/**
 * Class PvpKitModel
 * <br>Represent {@code Human}s slapped for choosing kit and displayed for previewing full kit
 * @package legionpe\games\kitpvp
 */
class PvpKitModel extends SlappableHuman{
	const NETWORK_ID = Human::NETWORK_ID;
	const IN_USE_TAG = TextFormat::GREEN . "{IN USE}" . TextFormat::WHITE . "\n";
	/** @var PvpGame */
	private $game;
	/** @var int */
	private $kid;
	/**
	 * @param PvpGame $game
	 * @param int $id
	 * @param null $loc
	 */
	public function __construct($game, $id, $loc = null){
		if(!($game instanceof PvpGame)){
			$this->badConstruct($game);
			return;
		}
		$this->game = $game;
		$this->kid = $id;
		parent::__construct($game->getMain(), $loc, "Kit $id");
	}
	protected function onSlap(Session $session){
		if($session->inSession($this->game)){
			$data = $this->game->getPlayerData($session);
			$curEditing = $data->getEditingKitId();
			if($curEditing !== $this->kid){ // choose to edit this
				$kit = $data->getKitById($this->kid);
				if($kit === null){ // create new kit
					if(count($data->getKits()) >= Settings::kitpvp_maxKits($session)){ // cannot create new kit: full
						$session->tell("You cannot use more kits! Upgrade your account to have more kits!");
						return;
					}
					// create new kit
					$kit = PvpKit::getDefault($session->getUID(), $this->kid);
					$data->addKit($this->kid, $kit);
				}
				/** @var PvpKitModel[] $others */
				$others = $this->game->getModels();
				if(isset($others[$curEditing])){
					$otherModel = $others[$curEditing];
					$otherModel->unfireTo($session->getPlayer());
				}
				$this->fireTo($session->getPlayer());
				$data->setEditingKitId($this->kid);
				$session->tell("You are now editing \"$kit->name\".");
				if($data->getKitIdInUse() !== $this->kid){
					$session->tell("Click me again to apply \"$kit->name\".");
				}
				else{
					$session->tell("Click me again to swap between bow/sword kit");
				}
			}
			else{ // make this the using kit
				if($data->getKitIdInUse() === $this->kid){
					if($data->getBowLevel() === 0){
						$session->tell("After you bought a bow, triple-click me to enable/disable it.");
						$session->tell("But right now you haven't yet!");
						return;
					}
					$data->setIsUsingBowKit($bool = !$data->isUsingBowKit());
					$session->tell("Your %s kit has been changed into a %s kit!", $bool ? "sword":"bow", $bool ? "bow":"sword");
					return;
				}
				$kit = $data->getKitById($this->kid);
				if($kit->kitid !== $this->kid){
					throw new \UnexpectedValueException("Unexpected $kit->kitid !== $this->kid at " . __FILE__ . "#" . __LINE__); // crash.
				}
				$this->setNameTagTo($session->getPlayer(), self::IN_USE_TAG . $this->getNameTag());
				$oldInUseId = $data->getKitIdInUse();
				$data->setKitIdInUse($this->kid);
				$others = $this->game->getModels();
				if(isset($others[$oldInUseId])){
					$otherModel = $others[$oldInUseId];
					$otherModel->setNameTagTo($session->getPlayer(), substr($otherModel->getNameTag(), strlen(self::IN_USE_TAG)));
				}
				$session->tell("You are now using kit \"$kit->name\". Rejoin this game or suicide using /kill to activate.");
				$data->getKitInUse()->equip($session->getPlayer()->getInventory(), $data, true);
			}
		}
		else{
			$this->getMain()->getLogger()->warning("$this was spawned to an invalid entity!");
			$this->despawnFrom($session->getPlayer());
		}
	}
	/**
	 * @param Player $player
	 * @param PvpSessionData|null $data
	 */
	public function spawnTo(Player $player, $data = null){
		if($data === null){
			$data = $this->game->getPlayerData($player, true); // most likely newly joined
			if(!($data instanceof PvpSessionData)){
				return; // don't spawn to players who aren't programmatically in KitPvP
			}
		}
		$kit = $data->getKitById($this->kid, true, $isNew);
		$this->getInventory()->sendContents($player);
		$this->setNameTag($kit->name === "" ? "Kit $this->kid":$kit->name);
		if($data->getKitIdInUse() === $this->kid){
			$this->nameTag = self::IN_USE_TAG . $this->nameTag;
		}
		parent::spawnTo($player);
		$kit->equip($this->getInventory(), $data, false); // TODO hardcode
		$this->getInventory()->sendArmorContents([$player]);
		$this->getInventory()->sendHeldItem($player);
		if($data->getEditingKitId() === $this->kid){
			$this->fireTo($player);
		}
	}
	public function fireTo(Player $player){
		$this->setDataFlagTo($player, self::DATA_FLAGS, self::DATA_FLAG_ONFIRE, true);
	}
	public function unfireTo(Player $player){
		$this->setDataFlagTo($player, self::DATA_FLAGS, self::DATA_FLAG_ONFIRE, false);
	}
	public function sneakTo(Player $player){
		$this->setDataFlagTo($player, self::DATA_FLAGS, self::DATA_FLAG_SNEAKING, true);
	}
	public function unsneakTo(Player $player){
		$this->setDataFlagTo($player, self::DATA_FLAGS, self::DATA_FLAG_SNEAKING, false);
	}
	public function getDrops(){
		return [];
	}
	public function __toString(){
		return parent::__toString() . "#$this->kid";
	}
}
