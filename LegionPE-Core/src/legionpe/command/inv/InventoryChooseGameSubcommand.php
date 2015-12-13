<?php

namespace legionpe\command\inv;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class InventoryChooseGameSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if($ses->openChooseGameInv()){
			return TextFormat::GREEN . "Open your inventory and click on an item to choose a game.";
		}
		return null;
	}
	public function getName(){
		return "choosegame";
	}
	public function getDescription(){
		return "Choose a game";
	}
	public function getUsage(){
		return "/inv cg";
	}
	public function getAliases(){
		return ["game", "cg"];
	}
}
