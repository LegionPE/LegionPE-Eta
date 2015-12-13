<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\level\Level;
use pocketmine\utils\TextFormat;

class TeleportWorldCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("tpw", "Teleport to a world's spawn", "/tpw <world>");
	}
	protected function run(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$lv = $this->getPlugin()->getServer()->getLevelByName(array_shift($args));
		if(!($lv instanceof Level)){
			return TextFormat::RED . "Such level doesn't exist.";
		}
		$ses->getPlayer()->teleport($lv->getSpawnLocation());
		return TextFormat::GREEN . "Teleported.";
	}
	public function checkPerm(Session $ses){
		return $ses->isMod();
	}
}
