<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class IgnoreCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("ignore", "Ignore chat messages and /tell messages from another player", "/ign <player>", ["ign"]);
	}
	protected function run(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$player = $this->getSession($name = array_shift($args));
		if(!($player instanceof Session)){
			return TextFormat::RED . "$name isn't online!";
		}
		if(stripos($ses->ignoring, "," . $player->getPlayer()->getName() . ",") !== false){
			return TextFormat::RED . "You are already ignoring $name!";
		}
		$ses->ignoring .= strtolower($player->getPlayer()->getName()) . ",";
		return TextFormat::GREEN . "You are now ignoring chat messages from $name.";
	}
}
