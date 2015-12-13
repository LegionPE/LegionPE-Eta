<?php

namespace legionpe\command\chan;

use legionpe\chat\Channel;
use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class ChanListSubcommand extends SessionSubcommand{
	public function checkPerm(Session $session){
		return $session->isMod();
	}
	protected function onRun(Session $ses, array $args){
		$this->main->getChannelManager()->tickClean();
		$output = "List of chat channels:\n";
		$ignorePlayer = true;
		if(isset($args[0])){
			if($args[0] === "v" or $args[0] === "verbose"){
				$ignorePlayer = true;
			}
		}
		foreach($this->main->getChannelManager()->listChannels() as $chan){
			if($ignorePlayer and $chan->getClass() === Channel::CLASS_PERSONAL){
				continue;
			}
			$output .= TextFormat::LIGHT_PURPLE . $chan->getName() . TextFormat::WHITE . "(" . TextFormat::BLUE . $chan->getClassAsString() . TextFormat::WHITE . ")" . ", ";
		}
		return substr($output, 0, -2);
	}
	public function getName(){
		return "list";
	}
	public function getDescription(){
		return "List all chat channels";
	}
	public function getUsage(){
		return "/ch l [v|verbose]";
	}
	public function getAliases(){
		return ["l"];
	}
}
