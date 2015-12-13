<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class TagCommand extends SessionCommand{
	protected function checkPerm(Session $session){
		return $session->getRank() > 0;
	}
	protected function cinit(){
		Command::__construct("tag", "Toggle chat tags", "/tag on|off|check");
	}
	protected function run(Session $ses, array $args){
		if(!isset($args[0])){
			$args = ["help"];
		}
		switch(array_shift($args)){
			case "on":
				$ses->getMysqlSession()->data["notag"] = 0;
				return TextFormat::GREEN . "Chat tags have been enabled.";
			case "off":
				$ses->getMysqlSession()->data["notag"] = 1;
				return TextFormat::GREEN . "Chat tags have been disabled.";
			case "check":
				return TextFormat::AQUA . "Your tag is " . (($ses->getMysqlSession()->data["notag"] === 0) ? "on":"off");
		}
		return TextFormat::RED . "Usage: " . $this->getUsage();
	}
}
