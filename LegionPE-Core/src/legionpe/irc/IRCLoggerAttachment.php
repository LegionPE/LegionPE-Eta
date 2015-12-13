<?php

namespace legionpe\irc;

use pocketmine\utils\TextFormat;

class IRCLoggerAttachment implements \LoggerAttachment{
	private $sender;
	public function __construct(IRCSender $sender){
		$this->sender = $sender;
	}
	public function log($level, $message){
		$this->sender->push(TextFormat::clean("[$level] $message"));
	}
}
