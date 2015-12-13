<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\config\Settings;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamCreateSubcommand extends SessionSubcommand{
	public function getName(){
		return "create";
	}
	public function getDescription(){
		return "Create a team";
	}
	public function getUsage(){
		return "/t create <team name>";
	}
	public function getAliases(){
		return ["new"];
	}
	protected function onRun(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		if($ses->getTeam() instanceof Team) {
			return TextFormat::RED . "You are alerady in a team!";
		}
		$name = array_shift($args);
		if(preg_match('#^[A-Za-z][A-Za-z0-9_\\-]{2,62}$#', $name) === 0){
			$red = TextFormat::RED;
			$yellow = TextFormat::YELLOW;
			$aqua = TextFormat::AQUA;
			return $red . "A team name must$yellow start with an alphabet$red, must$yellow only contain$aqua alphabets$red,$aqua numerals$red,$aqua underscore$red and$aqua hyphens$red, must be$yellow at least 3 characters$red long and$yellow at most 63 characters$red long.";
		}
		if($this->main->getTeamManager()->getTeamByExactName($name) instanceof Team){
			return TextFormat::RED . "A team with this name already exists!";
		}
		$team = new Team($this->main, $this->main->getMySQLi()->nextTID(), $name, Settings::team_maxCapacity($ses->getRank()), false, [$ses->getUID() => Team::RANK_LEADER]);
		$ses->getMysqlSession()->data["tid"] = $team->tid;
		$ses->getMysqlSession()->data["teamrank"] = Team::RANK_LEADER;
		$ses->getMysqlSession()->data["teamjointime"] = time();
		$this->main->getTeamManager()->addTeam($team);
		$ses->getPlayer()->kick(TextFormat::YELLOW . "You have been kicked from the server in order to apply your changes about creating a team.", false);
		return null;
	}
	protected function checkPerm(Session $session){
		return ($session->getImportanceRank() & Settings::RANK_IMPORTANCE_DONATOR) > 0;
	}
}
