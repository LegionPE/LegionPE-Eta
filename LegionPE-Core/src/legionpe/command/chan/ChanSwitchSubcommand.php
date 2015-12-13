<?php

namespace legionpe\command\chan;

use legionpe\chat\Channel;
use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanSwitchSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$chan = $this->main->getChannelManager()->getChannel($ch = array_shift($args));
		if(!($chan instanceof Channel)){
			return TextFormat::RED . "There is no channel by that name.";
		}
		if(!$chan->isSubscribing($ses) and !$ses->isOper()){
			return TextFormat::RED . "You must be a chat op to talk on channels you aren't subscribing to.";
		}
		if($ses->getWriteToChannel() === $chan){
			return TextFormat::RED . "You are already writing on that channel.";
		}
		$ses->setWriteToChannel($chan);
		return TextFormat::GREEN . "You are now writing on #$chan";
	}
	public function getName(){
		return "switch";
	}
	public function getDescription(){
		return "Switch the chat channel you are " . TextFormat::LIGHT_PURPLE . "talking" . TextFormat::DARK_GREEN . " on";
	}
	public function getUsage(){
		return "/ch s <channel name>";
	}
	public function getAliases(){
		return ["s"];
	}
}
