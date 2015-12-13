<?php

namespace legionpe\command\session;

use legionpe\config\Settings;
use legionpe\session\Session;
use legionpe\utils\MUtils;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class GrindCoinCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("grindcoin", "Enable coins grinding", "/gc", ["gc"]);
	}
	protected function run(Session $ses, array $args){
		if(!$ses->canActivateGrindCoins($secs)){
			return TextFormat::RED . "You need to wait for at least " . MUtils::time_secsToString($secs) . " to activate coins grinding again.";
		}
		if(!$ses->wannaGrind){
			$ses->wannaGrind = true;
			$ses->tell(TextFormat::AQUA . "After enabling coins grinding, coins you received will be multiplied by %s times. This doesn't apply to spending coins.", TextFormat::LIGHT_PURPLE . Settings::coinsFactor($ses, true) . TextFormat::AQUA);
			$ses->tell(TextFormat::AQUA . "It will last for %s each time, and you can't enable it again %s after activation.", TextFormat::LIGHT_PURPLE . MUtils::time_secsToString(Settings::getGrindDuration($ses)) . TextFormat::AQUA, TextFormat::LIGHT_PURPLE . MUtils::time_secsToString(Settings::getGrindActivationWaiting($ses)) . TextFormat::AQUA);
			return TextFormat::AQUA . "Run /grindcoin again to confirm enabling coins grinding.";
		}
		$ses->wannaGrind = false;
		$ses->getMysqlSession()->data["lastgrind"] = time();
		return TextFormat::GREEN . "You have activated coins grinding. You will receive an extra of " . TextFormat::LIGHT_PURPLE . (Settings::coinsFactor($ses) * 100 - 100) . "%" . TextFormat::GREEN . " coins for those you earn in the following " . TextFormat::LIGHT_PURPLE . MUtils::time_secsToString(Settings::getGrindDuration($ses)) . TextFormat::GREEN . ".";
	}
	protected function checkPerm(Session $ses){
		return ($ses->getImportanceRank() & Settings::RANK_IMPORTANCE_DONATOR) > 0;
	}
}
