<?php

namespace legionpe\command;

use legionpe\LegionPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;

class CallbackCommand extends Command implements PluginIdentifiableCommand{
	private $main;
	private $callback, $permCheck;
	public function __construct(LegionPE $main, $name, callable $callback, callable $permCheck, $desc = "", $usage = null, $aliases = []){
		$this->main = $main;
		if(is_string($aliases)){
			$aliases = [$aliases];
		}
		parent::__construct($name, $desc, $usage, $aliases);
		$this->callback = $callback;
		$this->permCheck = $permCheck;
	}
	public function execute(CommandSender $sender, $alias, array $args){
		if(call_user_func($this->permCheck, $this->main, $sender)){
			call_user_func($this->callback, $this->main, $this, $args, $sender, $alias);
		}
		else{
			$sender->sendMessage($this->getPermissionMessage());
		}
	}
	public function getPlugin(){
		return $this->main;
	}
}
