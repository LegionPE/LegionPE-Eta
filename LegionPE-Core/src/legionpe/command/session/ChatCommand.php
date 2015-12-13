<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;

class ChatCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("chat", "Turn chat on/off", "/chat on|off [channel]");
	}
	protected function run(Session $ses, array $args){
		$args = array_filter($args);
		if(!isset($args[0])){
			return "Usage: " . $this->getUsage();
		}
		if(count($args) === 1){
			$isOn = $ses->isChatOn();
			$req = $this->strBool($args[0], true);
			if($req === $isOn){
				return "Your chat is already {$this->boolStr($req)}!";
			}
			$ses->setChat($req);
			return "Your chat is now {$this->boolStr($req)}. Note that you may still be not listening to some channels if you have used /chat off <channel>.";
		}
		$req = $this->strBool(array_shift($args), true);
		$chan = ltrim(array_shift($args), "#");
		$isOn = !$ses->isIgnoringChannel($chan);
		if($req === $isOn){
			return "You were {$this->boolNot(!$req)}ignoring chat from #$chan!";
		}
		$req ? $ses->unignoreChannel($chan) : $ses->ignoreChannel($chan);
		return "You are now {$this->boolNot(!$req)}ignoring chat from #$chan.";
	}
}
