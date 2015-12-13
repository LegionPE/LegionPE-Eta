<?php

namespace legionpe\command\sublib;

use pocketmine\command\CommandSender;

abstract class Subcommand{
	/** @var SubcommandMap */
	public $map;
	/** @var \legionpe\LegionPE */
	public $main;
	public abstract function getName();
	public abstract function getDescription();
	public abstract function getUsage();
	public function getAliases(){
		return [];
	}
	public function getPermissionMessage(){
		return "You don't have permission to use this command.";
	}
	public abstract function run(CommandSender $sender, array $args);
	public function hasPerm(/** @noinspection PhpUnusedParameterInspection */ CommandSender $sender){
		return true;
	}
}
