<?php

namespace legionpe\command\session;

use legionpe\LegionPE;
use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;

abstract class SessionCommand extends Command implements PluginIdentifiableCommand{
	/** @var LegionPE */
	private $main;
	public function __construct(LegionPE $main){
		$this->main = $main;
		$this->cinit();
	}
	public function execute(CommandSender $sender, $l, array $args){
		if(!($sender instanceof Player)){
			$sender->sendMessage("Please run this command in-game.");
			return false;
		}
		if(!$this->testPermission($sender)){
			return false;
		}
		$ses = $this->main->getSessions()->getSession($sender);
		if(!($ses instanceof Session)){
			$ses->tell("Your session has not been initialized yet.");
			return false;
		}
		if(is_string($r = $this->run($ses, $args))){
			$ses->tell($r);
		}
		return true;
	}
	public function getPlugin(){
		return $this->main;
	}
	public function testPermissionSilent(CommandSender $sender){
		if(!($sender instanceof Player)){
			return false;
		}
		$ses = $this->getSession($sender);
		if(!($ses instanceof Session)){
			return false;
		}
		return $this->checkPerm($ses);
	}
	protected function checkPerm(/** @noinspection PhpUnusedParameterInspection */ Session $ses){
		return true;
	}
	protected abstract function cinit();
	protected abstract function run(Session $ses, array $args);
	/**
	 * @param Player|string $player
	 * @return Session|null
	 */
	protected function getSession($player){
		$ses = $this->main->getSessions()->getSession($player);
		return ($ses instanceof Session) ? $ses:null;
	}
	protected function boolStr($bool){
		return $bool ? "enabled":"disabled";
	}
	protected function boolNot($bool){
		return $bool ? "":"not ";
	}
	protected function strBool($str, $default = false){
		if(in_array($str, ["on", "enable", "yes", "enabled", "true", "open"])){
			return true;
		}
		if(in_array($str, ["off", "disable", "no", "disabled", "false", "close"])){
			return false;
		}
		return $default;
	}
}
