<?php

namespace legionpe\command\info;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class InfoSessionSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		$session = "UNKNOWN";
		foreach((new \ReflectionClass(Session::class))->getConstants() as $name => $value){
			if($value === $ses->getSessionId()){
				$session = $name;
				break;
			}
		}
		return TextFormat::AQUA . "You are in session " . $session . "({$ses->getSessionId()}";
	}
	public function getName(){
		return "session";
	}
	public function getDescription(){
		return "Show technical information about your session";
	}
	public function getUsage(){
		return "/info ses";
	}
	public function getAliases(){
		return ["ses"];
	}
}
