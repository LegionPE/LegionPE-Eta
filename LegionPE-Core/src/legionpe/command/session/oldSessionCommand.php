<?php

namespace legionpe\command\session;

use legionpe\LegionPE;
use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;

class oldSessionCommand extends Command implements PluginIdentifiableCommand{
	private $main;
	public function __construct(LegionPE $main, $name, $desc = "", $usage = null, $alias = []){
		parent::__construct($name, $desc, $usage, (array) $alias);
		$this->main = $main;
	}
	public function execute(CommandSender $sender, $alias, array $args){
		/** @var Session $session */
		if($this->testPermission($sender, $session)){
			$session->tell(str_repeat("~", 32));
			$session->onCommand($this, $args);
			$session->tell(str_repeat("~", 32));
			return true;
		}
		return false;
	}
	public function testPermission(CommandSender $sender, Session &$session = null){
		if($sender instanceof Player){
			$session = $this->getPlugin()->getSessions()->getSession($sender);
			if($session instanceof Session){
				if($session->testCommandPermission($this, $msg)){
					return true;
				}
				$sender->sendMessage($msg);
				return false;
			}
			else{
				$sender->sendMessage("Sorry, session not initialized.");
			}
		}
		$sender->sendMessage("Please run this command in-game.");
		return false;
	}
	public function getPlugin(){
		return $this->main;
	}
	public function tellWrongUsage(Session $session){
		$session->tell("Wrong usage. Usage: " . $this->getUsage());
	}
}
