<?php

namespace legionpe\command\chan;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanSubscribingSubcommand extends SessionSubcommand{
	public function onRun(Session $session, array $args){
		$output = "List of subscribing chat channels:\n";
		foreach($this->main->getChannelManager()->listChannels() as $chan){
			if($chan->isSubscribing($session)){
				$output .= TextFormat::LIGHT_PURPLE . $chan->getName() . TextFormat::WHITE . "(" . TextFormat::BLUE . $chan->getClassAsString() . TextFormat::WHITE . ")" . ", ";
			}
		}
		return substr($output, 0, -2);
	}
	public function getName(){
		return "subscribing";
	}
	public function getDescription(){
		return "List all chat channels you are subscribing to";
	}
	public function getUsage(){
		return "/ch sub";
	}
	public function getAliases(){
		return ["sub"];
	}
}
