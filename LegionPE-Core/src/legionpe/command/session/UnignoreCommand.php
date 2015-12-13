<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class UnignoreCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("unignore", "Unignore a player", "/unignore <player>", ["uign"]);
	}
	protected function run(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Wrong usage. Usage: /unignore <player>";
		}
		$id = ($name = array_shift($args)) . ",";
		$pos = stripos($ses->ignoring, "," . $id);
		if($pos === false){
			return TextFormat::RED . "You were not ignoring $name!";
		}
		$ses->ignoring = substr($ses->ignoring, 0, $pos) . substr($ses->ignoring, $pos + strlen($id)); // delete the comma in front and the name; leave the comma at the back
		return TextFormat::GREEN . "You are no longer ignoring chat messages from $name.";
	}
}
