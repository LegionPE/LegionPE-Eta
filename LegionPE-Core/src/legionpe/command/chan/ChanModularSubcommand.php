<?php

namespace legionpe\command\chan;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\games\Game;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanModularSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(($game = $ses->getGame()) instanceof Game){
			$ch = $game->getDefaultChatChannel();
		}else{
			$ch = $this->main->getDefaultChannel();
		}
		if(!$ch->isSubscribing($ses)){
			return TextFormat::RED . "It seems like you were kicked from your current game's or hub's chat channel.";
		}
		$ses->setWriteToChannel($ch);
		return TextFormat::GREEN . "You are now talking on #$ch.";
	}
	public function getName(){
		return "modular";
	}
	public function getDescription(){
		return "Switch to current modular (game or hub) channel";
	}
	public function getUsage(){
		return "/ch m";
	}
	public function getAliases(){
		return ["m", "g", "h", "game", "hub"];
	}
}
