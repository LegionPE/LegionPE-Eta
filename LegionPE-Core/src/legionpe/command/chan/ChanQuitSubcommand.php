<?php

namespace legionpe\command\chan;

use legionpe\chat\Channel;
use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanQuitSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$chan = $this->main->getChannelManager()->getChannel($ch = array_shift($args));
		if(!($chan instanceof Channel)){
			return TextFormat::RED . "Such channel doesn't exist.";
		}
		if(!$chan->isSubscribing($ses)){
			return TextFormat::RED . "You aren't subscribing to that channel.";
		}
		if($chan->getClass() !== Channel::CLASS_CUSTOM){
			$type = $chan->getClassAsString();
			return TextFormat::RED . "This command only supports custom channels, not $type channels. Use " . TextFormat::AQUA . "/chat off $ch" . TextFormat::RED . " if you want to ignore chat messages from a $type channel.";
		}
		$ses->unsubscribeFromChannel($chan);
		return TextFormat::GREEN . "You have successfully quitted chat channel #$chan.";
	}
	public function getName(){
		return "quit";
	}
	public function getDescription(){
		return "Quit a chat channel";
	}
	public function getUsage(){
		return "/ch q <channel name>";
	}
	public function getAliases(){
		return ["q", "part", "leave"];
	}
}
