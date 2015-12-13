<?php

namespace legionpe\command\info;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class InfoUidSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		return TextFormat::AQUA . "Your user ID is " . $ses->getUID() . ".";
	}
	public function getName(){
		return "uid";
	}
	public function getDescription(){
		return "Show your user ID";
	}
	public function getUsage(){
		return "/info uid";
	}
}
