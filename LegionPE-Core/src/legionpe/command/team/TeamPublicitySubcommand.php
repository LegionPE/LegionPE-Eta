<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamPublicitySubcommand extends SessionSubcommand{
	/** @var bool */
	private $open;
	public function __construct($open){
		$this->open = $open;
	}
	protected function onRun(Session $ses, array $args){
		if(!(($team = $ses->getTeam()) instanceof Team)){
			return TextFormat::RED . "You aren't in a team!";
		}
		if($ses->getTeamRank() < Team::RANK_CO_LEADER){
			return TextFormat::RED . "You must be a leader or a co-leader to change the state of your team!";
		}
		if($team->open === $this->open){
			return TextFormat::RED . "Your team is already a " . TextFormat::LIGHT_PURPLE . ($this->open ? "open":"closed") . TextFormat::GREEN . " team!";
		}
		$team->open = $this->open;
		return TextFormat::GREEN . "Your team is now a " . TextFormat::LIGHT_PURPLE . ($this->open ? "open":"closed") . TextFormat::GREEN . " team.";
	}
	public function getName(){
		return $this->open ? "open":"close";
	}
	public function getDescription(){
		return ucfirst($this->open) . " a team";
	}
	public function getUsage(){
		return "/t " . $this->getName();
	}
}
