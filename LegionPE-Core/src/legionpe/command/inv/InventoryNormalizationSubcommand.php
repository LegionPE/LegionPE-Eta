<?php

namespace legionpe\command\inv;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class InventoryNormalizationSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if($ses->getInventorySession() === Session::INV_NORMAL_ACCESS){
			return TextFormat::RED . "Your inventory type is already at normal access";
		}
		if(in_array($ses->getInventorySession(), [Session::INV_CHOOSE_GAME])){
			$ses->invSession = Session::INV_NORMAL_ACCESS;
			return TextFormat::GREEN . "Your inventory type has been normalized.";
		}
		return TextFormat::YELLOW . "Error! Your inventory type is unknown!";
	}
	public function getName(){
		return "normalize";
	}
	public function getDescription(){
		return "Change your inventory type into normal inventory access (instead of GUI inventory access)";
	}
	public function getUsage(){
		return "/inv norm";
	}
	public function getAliases(){
		return ["norm"];
	}
}
