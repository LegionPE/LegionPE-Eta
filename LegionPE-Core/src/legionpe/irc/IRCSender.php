<?php

namespace legionpe\irc;

use pocketmine\Thread;
use pocketmine\utils\Utils;

class IRCSender extends Thread{
	const WEBHOOK = "http://n.tkte.ch/h/3723/***?payload=";
	public $lock = false;
	public $msgs;
	public $running;
	public function __construct(){
		$this->msgs = serialize([]);
	}
	public function run(){
		while($this->running){
			$this->tick();
		}
	}
	private function tick(){
		while(is_string($text = $this->next())){
			echo "Sending message $text\r\n";
			Utils::getURL(self::WEBHOOK . urlencode($text));
		}
	}
	public function next(){
		$this->acquire();
		$msgs = unserialize($this->msgs);
		if(count($msgs) === 0){
			$result = null;
		}
		else{
			$result = array_shift($msgs);
			$this->msgs = serialize($msgs);
		}
		$this->release();
		return $result;
	}
	public function push($msg){
		$this->acquire();
		$msgs = unserialize($this->msgs);
		$msgs[] = $msg;
		$this->msgs = serialize($msgs);
		$this->release();
	}
	public function acquire(){
		while($this->lock);
		$this->lock = true;
	}
	public function release(){
		$this->lock = false;
	}
}
