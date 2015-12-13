<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamPropertyChangeSubcommand extends SessionSubcommand{
	/** @var string */
	private $fieldName, $humanName;
	/** @var string[] */
	private $aliases;
	protected function __construct($fieldName, $humanName, $aliases = []){
		$this->fieldName = $fieldName;
		$this->humanName = $humanName;
		$this->aliases = $aliases;
	}
	protected function onRun(Session $ses, array $args){
		if(!(($team = $ses->getTeam()) instanceof Team)){
			return TextFormat::RED . "You are not in a team!";
		}
		$value = trim(implode(" ", array_filter($args)));
		$field = $this->fieldName;
		if($value === ""){
			return TextFormat::GREEN . ucfirst($this->humanName) . " for Team $team:\n" . TextFormat::RESET . str_replace("\n", "\n" . TextFormat::RESET, $team->$field);
		}
		$value = preg_replace('/ ?\| ?/', "\n", $value);
		$team->$field = $value;
		return TextFormat::GREEN . "The $this->humanName for Team $team has been changed to:\n" . TextFormat::RESET . str_replace("\n", "\n" . TextFormat::RESET, $value);
	}
	public function getName(){
		return $this->fieldName;
	}
	public function getDescription(){
		return "View/Change team $this->humanName";
	}
	public function getUsage(){
		return "/t $this->fieldName [new team $this->humanName]";
	}
	public function getAliases(){
		return $this->aliases;
	}
	public static function rules(){
		return new self("rules", "rules", ["rul", "rule"]);
	}
	public static function requires(){
		return new self("requires", "requirements", ["require", "req", "requirement", "requirements"]);
	}
}
