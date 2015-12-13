<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class MyKickCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("mk", "LegionPE-style kicking (NOT implemented)", "/mk <player> <message>");
	}
	protected function run(Session $ses, array $args){
		return TextFormat::RED . "Use /kick instead. This command remains here for legacy purposes.";
	}
	protected function checkPerm(Session $ses){
		return $ses->isMod();
	}
}
