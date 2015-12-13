<?php

namespace legionpe\utils;

use legionpe\LegionPE;
use pocketmine\scheduler\PluginTask;

class CallbackPluginTask extends PluginTask{
	/** @var mixed[] */
	private $args;
	/** @var callable */
	private $callback;
	public function __construct(LegionPE $main, callable $callback, ...$args){
		parent::__construct($main);
		$this->callback = $callback;
		$this->args = $args;
	}
	public function onRun($ticks){
		call_user_func($this->callback, ...$this->args);
	}
}
