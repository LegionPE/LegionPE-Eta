<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamRankChangeSubcommand extends SessionSubcommand{
	/** @var bool */
	private $promote;
	/**
	 * @param bool $promote
	 */
	public function __construct($promote){
		$this->promote = $promote;
	}
	protected function onRun(Session $ses, array $args){
		$team = $ses->getTeam();
		if(!($team instanceof Team)){
			return TextFormat::RED . "You are not in a team!";
		}
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$target = $this->getSession(array_shift($args));
		if(!($target instanceof Session)){
			return TextFormat::RED . "There is no player online by that name!";
		}
		if(!isset($team->members[$target->getUID()])){
			return TextFormat::RED . $target->getRealName() . " is not in your team!";
		}
		$myRank = $ses->getTeamRank();
		$lightPurple = TextFormat::LIGHT_PURPLE;
		$red = TextFormat::RED;
		$aqua = TextFormat::AQUA;
		if($myRank < Team::RANK_CO_LEADER){
			return $red . "You must be$lightPurple a team leader or co-leader$red to promote/demote players!";
		}
		$hisRank = $ses->getTeamRank();
		if($hisRank === Team::RANK_CO_LEADER and $this->promote){
			return TextFormat::RED . "There can only be one leader per team, and the leadership cannot be transferred. \n$aqua" . "You can contact an$lightPurple admin$aqua, a$lightPurple developer$aqua or an$lightPurple owner$aqua if you have special reasons.";
		}
		if($hisRank === Team::RANK_JUNIOR and !$this->promote){
			return TextFormat::RED . "Junior-Member is already the lowest rank. \n$aqua" . "Use $lightPurple/t k$aqua if you wish to kick the player.";
		}
		if($hisRank >= $myRank){
			return TextFormat::RED . "You can only promote/demote members of lower rank than you!";
		}
		$team->members[$target->getUID()] = $target->getMysqlSession()->data["teamrank"] = ($this->promote ? ++$hisRank : --$hisRank);
		$rankName = Team::$RANK_NAMES[$hisRank];
		$target->tell(TextFormat::AQUA . "You have been " . $this->getName() . "d to a$lightPurple $rankName$aqua by " . $ses->getRealName() . ". \n" . TextFormat::GREEN . "If you wish to have your nametag updated, please rejoin.\n$aqua" . "If you don't mind, you don't need to rejoin; your nametag will be updated the next time you join."); // TODO $session->recalculateNametag()
		return TextFormat::GREEN . $target->getRealName() . " has been " . $this->getName() . "d to a$lightPurple $rankName.";
	}
	public function getName(){
		return $this->promote ? "promote":"demote";
	}
	public function getDescription(){
		return ucfirst($this->getName()) . " a team member";
	}
	public function getUsage(){
		return "/t " . $this->getName() . " <member>";
	}
}
