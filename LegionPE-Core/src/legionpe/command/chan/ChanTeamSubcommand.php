<?php

namespace legionpe\command\chan;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class ChanTeamSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(!(($team = $ses->getTeam()) instanceof Team)){
			return TextFormat::RED . "You aren't in a team!";
		}
		$ses->setWriteToChannel($ch = $team->getChannel());
		return TextFormat::GREEN . "You are now talking on #$ch.";
	}
	public function getName(){
		return "team";
	}
	public function getDescription(){
		return "Change to team channel";
	}
	public function getUsage(){
		return "/ch t";
	}
	public function getAliases(){
		return ["t"];
	}
}
