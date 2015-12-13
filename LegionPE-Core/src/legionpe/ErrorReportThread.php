<?php

namespace legionpe;

use pocketmine\Thread;
use pocketmine\utils\Utils;

class ErrorReportThread extends Thread{
	public $lock = false;
	public $send = "";
	public function run(){
		while(true){
			$this->acquire();
			if($this->send){
				$lines = explode("\n", $this->send);
				$this->send = "";
			}
			$this->release();
			if(isset($lines)){
				foreach($lines as $l){
					Utils::getURL(LegionPE::IRC_WEBHOOK . urlencode($l));
				}
			}
		}
	}
	public function acquire(){
		while($this->lock);
		$this->lock = true;
	}
	public function release(){
		$this->lock = false;
	}
	public function post($line){
		$this->acquire();
		$this->send .= $line . "\n";
		$this->release();
	}
}
