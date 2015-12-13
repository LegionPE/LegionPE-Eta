<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamListSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		return implode(", ", array_map(function(Team $team){
			return TextFormat::RED . $team->name . TextFormat::WHITE . " (" . TextFormat::GOLD . count($team->members) . TextFormat::WHITE . "/" . TextFormat::YELLOW . $team->maxCapacity . TextFormat::WHITE . ", " . ($team->open ? (TextFormat::DARK_GREEN . "open") : "") . TextFormat::WHITE . ")";
		}, $this->main->getTeamManager()->getTeams()));
	}
	public function getName(){
		return "list";
	}
	public function getDescription(){
		return "List all teams on the server.";
	}
	public function getUsage(){
		return "/t l";
	}
	public function getAlaises(){
		return ["l"];
	}
}
