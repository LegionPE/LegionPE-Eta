<?php

namespace legionpe\command\chan;

use legionpe\chat\Channel;
use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanKickSubcommand extends SessionSubcommand{
	protected function onRun(Session $from, array $args){
		if(!isset($args[1])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$ch = $this->main->getChannelManager()->getChannel(array_shift($args));
		if(!($ch instanceof Channel)){
			return TextFormat::RED . "There is no channel by that name.";
		}
		if(!(($ses = $this->getSession(array_shift($args))) instanceof Session)){
			return TextFormat::RED . "There is no player online by that name.";
		}
		if(!$ch->isSubscribing($ses)){
			return TextFormat::RED . "$ses isn't subscribing to #$ch.";
		}
		$ch->kick($from, $ses);
		return null;
	}
	public function getName(){
		return "kick";
	}
	public function getDescription(){
		return "Kick a subscriber from a chat channel";
	}
	public function getUsage(){
		return "/ch k <channel name> <player>";
	}
	public function getAliases(){
		return ["k"];
	}
}
