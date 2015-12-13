<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\MysqlConnection;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamQuitSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		$team = $ses->getTeam();
		if(!($team instanceof Team)){
			return TextFormat::RED . "You are not in a team!";
		}
		if(!$ses->quitTeamConfirmed){
			$ses->quitTeamConfirmed = true;
			return TextFormat::YELLOW . "Are you sure to quit your team? You may not be able to join the team again! If you are the leader, you are going to disband the team! Run \"/team q\" again to confirm.";
		}
		$ses->quitTeamConfirmed = false;
		if($ses->getTeamRank() === Team::RANK_LEADER){
			$team->tell(TextFormat::YELLOW . "Team $team is disbanding because the leader is quitting the team! You are going to be kicked to apply the changes.");
			/** @var Session $ses */
			foreach($team->getSessionsOnline() as $ses){
				$team->quit($ses);
				$ses->getPlayer()->kick(TextFormat::YELLOW . "Your team has been disbanded. You have been kicked to apply the changes", false);
			}
			$this->main->getMySQLi()->query("UPDATE PLAYERS SET tid=-1,teamrank=0,teamjointime=0 WHERE tid=$team->tid", MysqlConnection::RAW);
			$this->main->getTeamManager()->rmTeam($team);
			return null;
		}
		$team->quit($ses);
		$ses->getPlayer()->kick(TextFormat::YELLOW . "You have quitted your team. You have been kicked to apply the changes.", false);
		return null;
	}
	public function getName(){
		return "quit";
	}
	public function getDescription(){
		return "Quit your team";
	}
	/**
	 * @return string
	 */
	public function getUsage(){
		return "/t q";
	}
	public function getAliases(){
		return ["leave", "exit", "logout", "q"];
	}
}
