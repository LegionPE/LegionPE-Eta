<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class MysqlBanCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("mb", "Execute MySQL-acknowledged ban on an online player (NOT implemented)", "/mb <player> [-t <days>] <message ...>");
	}
	protected function run(Session $ses, array $args){
		// TODO
		return TextFormat::RED . "This command has not been implemented yet. Please use one of the /w*** commands instead.";
	}
	protected function checkPerm(Session $ses){
		return $ses->isMod();
	}
}
