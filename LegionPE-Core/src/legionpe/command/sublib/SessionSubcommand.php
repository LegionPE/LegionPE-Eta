<?php

namespace legionpe\command\sublib;

use legionpe\session\Session;
use pocketmine\command\CommandSender;
use pocketmine\Player;

abstract class SessionSubcommand extends Subcommand{
	public function hasPerm(CommandSender $sender){
		if(!($sender instanceof Player)){
			return false;
		}
		$ses = $this->getSession($sender);
		if(!($ses instanceof Session)){
			return false;
		}
		return $this->checkPerm($ses);
	}
	public function run(CommandSender $sender, array $args){
		$ses = $this->getSession($sender);
		return $this->onRun($ses, $args);
	}
	protected abstract function onRun(Session $ses, array $args);
	protected function checkPerm(/** @noinspection PhpUnusedParameterInspection */ Session $session){
		return true;
	}
	protected function getSession($player){
		$ses = $this->main->getSessions()->getSession($player);
		return $ses instanceof Session ? $ses : null;
	}
}
