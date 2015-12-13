<?php

namespace legionpe\games\kitpvp;

use legionpe\config\Settings;
use legionpe\session\Session;
use legionpe\utils\MUtils;
use legionpe\utils\SlappableHuman;
use pocketmine\item\Bow;
use pocketmine\level\Location;

class PvpBowHolder extends SlappableHuman{
	private $game;
	/**
	 * @param Location $loc
	 * @param PvpGame $game
	 */
	public function __construct($loc, $game){
		if(!($loc instanceof Location) or !($game instanceof PvpGame)){
			$this->badConstruct($loc);
			return;
		}
		$this->game = $game;
		$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_ACTION, true);
		parent::__construct($game->getMain(), $loc, " Upgrade\nYour bow");
		$this->getInventory()->setItem(0, new Bow);
		$this->getInventory()->setHeldItemSlot(0);
	}
	public function onSlap(Session $session){
		$data = $this->game->getPlayerData($session);
		$oldLevel = $data->getBowLevel();
		$newLevel = $oldLevel + 1;
		list($upgradePrice, $name, $description, $damage, $fireTicks, $knockback, $minImportance) = Settings::kitpvp_getBowInfo($newLevel);
		$rank = $session->getRank();
		if(($rank & Settings::RANK_SECTOR_IMPORTANCE) < $minImportance){
			$session->tell("Upgrade your account to get more advanced bows!");
			return;
		}
		if($upgradePrice === PHP_INT_MAX){
			$session->tell("More bow upgrades coming soon!");
			return;
		}
		if($data->isGoingToBuy === null or $data->isGoingToBuy["column"] !== "bow" or $data->isGoingToBuy["timestamp"] + 5 < microtime(true)){
			$session->tell("Upgrade your bow to \"%s\".", $name);
			$session->tell($description);
			$session->tell("%s deals %f hearts of damage to victims.", $name, $damage / 2);
			if($fireTicks > 0){
				$session->tell("It also sets them on fire for %f seconds", $fireTicks / 20);
			}
			if($knockback > 0){
				$session->tell("An extra knockback of $knockback%% will also be casted.");
			}
			$session->tell("This upgrade costs you %g coins.", $upgradePrice);
			if($upgradePrice > $session->getCoins()){
				$session->tell("You need at least %g more coins to upgrade your bow!", $upgradePrice - $session->getCoins());
			}
			else{
				$session->tell("Click me within 5 seconds to confirm the purchase.");
				$data->isGoingToBuy = [
					"column" => "bow",
					"timestamp" => microtime(true)
				];
			}
		}
		else{
			$data->isGoingToBuy = null;
			if($upgradePrice > $session->getCoins()){
				$session->tell("You need at least %d more coins to upgrade your bow!", $upgradePrice - $session->getCoins());
				return;
			}
			$session->setCoins($coins = $session->getCoins() - $upgradePrice);
			$data->setBowLevel($newLevel);
			MUtils::word_addSingularArticle($name);
			$session->tell("Your bow is now $name! You have %d coins left.", $coins);
			$session->tell("Triple-click a model to toggle sword or bow.");
		}
	}
	public function __toString(){
		return "PvpBowHolder (eid $this->id)";
	}
}
