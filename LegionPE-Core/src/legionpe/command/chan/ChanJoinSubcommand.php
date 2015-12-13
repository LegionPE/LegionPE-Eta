<?php

namespace legionpe\command\chan;

use legionpe\chat\Channel;
use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanJoinSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(!isset($args[0])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$chan = $this->main->getChannelManager()->joinChannel($ses, $ch = array_shift($args), Channel::CLASS_CUSTOM, true, !$ses->isOper());
		if($chan === false){
			return TextFormat::RED . "That channel cannot be joined freely.";
		}elseif($chan->getOwner() === $ses){
			$ses->tell(TextFormat::GREEN . "You have created and gained ownership of chat channel #$chan.");
		}else{
			$ses->tell(TextFormat::GREEN . "You have joined channel #$chan.");
		}
		$chan->broadcast("$ses joined the chat channel.", Channel::LEVEL_VERBOSE_INFO);
		$ses->setWriteToChannel($chan);
		return TextFormat::AQUA . "You are now talking on #$chan.";
	}
	public function getName(){
		return "join";
	}
	public function getDescription(){
		return "Join a chat channel";
	}
	public function getUsage(){
		return "/ch j <channel name>";
	}
	public function getAliases(){
		return ["j"];
	}
}
