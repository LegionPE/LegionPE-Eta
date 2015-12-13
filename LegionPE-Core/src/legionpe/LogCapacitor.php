<?php

namespace legionpe;


use pocketmine\utils\MainLogger;

class LogCapacitor{
	private static $msgs = [];
	public static function log($logger, $flag, $msg, $timeout = 5){
		if(isset(self::$msgs[$flag])){
			if(microtime(true) - self::$msgs[$flag] < $timeout){
				return;
			}
		}
		self::$msgs[$flag] = microtime(true);
		if($logger instanceof \Logger){
			$logger->notice($msg);
		}
		else{
			MainLogger::getLogger()->notice($msg);
		}
	}
}
