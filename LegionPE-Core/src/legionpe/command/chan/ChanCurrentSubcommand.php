<?php

namespace legionpe\command\chan;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanCurrentSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		return TextFormat::AQUA . "You are currently talking on #" . $ses->getWriteToChannel() . ".";
	}
	public function getName(){
		return "current";
	}
	public function getDescription(){
		return "See the name of the channel you are talking on";
	}
	public function getUsage(){
		return "/ch cur";
	}
	public function getAliases(){
		return ["cur", "curr"];
	}
}
