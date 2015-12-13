<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;

class AuthCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("auth", "Configure auth settings", "/auth <option> [value]");
	}
	protected function run(Session $ses, array $args){
		if(!isset($args[0])){
			$ses->tell("Usage: /auth <option> [value]");
			goto listopts;
		}
		$opt = array_shift($args);
		$value = array_shift($args);
		switch($opt){
			case "lastip":
				if($value === "yes"){
					$ses->getMysqlSession()->data["ipconfig"] = Session::IPCONFIG_LASTIP;
					return "You are now going to be authenticated by your last IP OR your password.";
				}
				if($value === "no"){
					$ses->getMysqlSession()->data["ipconfig"] = Session::IPCONFIG_DISABLE;
					return "You are now going to be authenticated by your password ONLY.";
				}
				return "Last-IP authentication is {$this->boolStr($ses->getMysqlSession()->data["ipconfig"] === Session::IPCONFIG_LASTIP)} for you.";
		}
		$ses->tell("Unknown option. Available options:");
		listopts:
		$ses->tell("_____________________");
		$ses->tell("| Option |  Value   |");
		$ses->tell("| lastip | no / yes |");
		$ses->tell("---------------------");
		return "Example: /auth ip no";
	}
}
