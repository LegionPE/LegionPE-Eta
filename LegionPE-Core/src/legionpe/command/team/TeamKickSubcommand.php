<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamKickSubcommand extends SessionSubcommand{
	public function onRun(Session $session, array $args){
		if(!($session->getTeam() instanceof Team)){
			return TextFormat::RED . "You aren't in a team!";
		}
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$kicked = $this->getSession(array_shift($args));
		if(!($kicked instanceof Session)){
			return TextFormat::RED . "There is no player online by that name.";
		}
		if($kicked === $session){
			return TextFormat::RED . "You can't kick yourself from your team!\n" . TextFormat::AQUA . "Use " . TextFormat::LIGHT_PURPLE . "/team rank" . TextFormat::AQUA . " instead.";
		}
		if($kicked->getTeam() !== $session->getTeam()){
			return TextFormat::RED . "$kicked is not in your team.";
		}
		if($session->getTeamRank() < Team::RANK_CO_LEADER){
			return TextFormat::RED . "You must be at least a Co-Leader to kick a team member.";
		}
		if($kicked->getTeamRank() >= $session->getTeamRank()){
			return TextFormat::RED . "You can only kick team members of lower rank than you.";
		}
		$session->getTeam()->quit($kicked);
		$session->getPlayer()->kick(TextFormat::YELLOW . "You have been kicked from your team. You are kicked from the server to apply the changes.", false);
		return "$session has been kicked from the team.";
	}
	public function getName(){
		return "kick";
	}
	public function getDescription(){
		return "Kick a player";
	}
	public function getUsage(){
		return "/t k <player>";
	}
	public function getAliases(){
		return ["k"];
	}
}
