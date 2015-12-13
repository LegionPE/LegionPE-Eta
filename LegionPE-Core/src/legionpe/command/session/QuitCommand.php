<?php

namespace legionpe\command\session;

use legionpe\config\Settings;
use legionpe\games\Game;
use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class QuitCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("quit", "Quit your current game", "/quit", ["hub", "spawn", "lobby", "back"]);
	}
	protected function run(Session $ses, array $args){
		if(!$ses->switchSession(Session::SESSION_GAME_HUB)){
			return TextFormat::RED . "Failed to quit - you are not in a game, or your game refused to let you go.";
		}
		$ses->teleport(Settings::loginSpawn($this->getPlugin()->getServer()));
		return TextFormat::GREEN . "You are now at hub.";
	}
	protected function checkPerm(Session $ses){
		return $ses->getGame() instanceof Game;
	}
}
