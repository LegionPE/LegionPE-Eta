<?php

namespace legionpe\command\session;

use legionpe\games\Game;
use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class StatisticsCommand extends SessionCommand{
	protected function checkPerm(Session $ses){
		return $ses->getGame() instanceof Game;
	}
	protected function cinit(){
		Command::__construct("stats", "Show your game statistics", "/stats <top> (usage may vary with your game)", ["stat", "statistics"]);
	}
	protected function run(Session $ses, array $args){
		$game = $ses->getGame();
		if($game instanceof Game){
			return $game->onStats($ses, $args);
		}
		else{
			return TextFormat::RED . "You aren't in a game.";
		}
	}
}
