<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;

class CoinsCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("coins", "Check your coins balance", "/coins", ["c"]);
	}
	protected function run(Session $ses, array $args){
		return "You have {$ses->getCoins()} coins left.";
	}
}
