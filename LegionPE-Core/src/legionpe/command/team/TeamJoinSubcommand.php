<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamJoinSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		if($ses->getTeam() instanceof Team){
			return TextFormat::RED . "You are already in a team!";
		}
		$team = $this->main->getTeamManager()->getTeamByName($name = array_shift($args));
		if(!($team instanceof Team)){
			return TextFormat::RED . "Team \"$name\" not found! Try using \"/team list\" to search discover teams.";
		}
		if($team->isFull()){
			return TextFormat::RED . "Team $team is already full!";
		}
		if(!$team->canJoin($ses)){
			return TextFormat::RED . "You must be invited to join the team!";
		}
		$team->tell(TextFormat::AQUA . "$ses joined the team!");
		$team->join($ses);
		if(isset($team->invited[$ses->getUID()])){
			unset($team->invited[$ses->getUID()]);
		}
		$ses->getPlayer()->kick(TextFormat::YELLOW . "You have joined Team $team. You have been kicked from the server to aply the changes.", false);
		return TextFormat::GREEN . "You have joined Team $team! You are going to be kicked to apply the changes.";
	}
	public function getName(){
		return "join";
	}
	public function getDescription(){
		return "Join a team";
	}
	public function getUsage(){
		return "/t j <team name>";
	}
	public function getAliases(){
		return ["j"];
	}
}
