<?php

namespace legionpe\command;

use legionpe\LegionPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginIdentifiableCommand;

class PhpCommand extends Command implements PluginIdentifiableCommand{
	private $plugin;
	public function __construct(LegionPE $plugin){
		$this->plugin = $plugin;
		parent::__construct("php", "Execute raw PHP code in legionpe\\LegionPE::evaluate() function context", "/php <PHP code>");
	}
	public function execute(CommandSender $sender, $label, array $args){
		if(!$this->testPermission($sender)){
			return;
		}
		$code = implode(" ", $args);
		$this->getPlugin()->getLogger()->info("Executing PHP code: $code");
		$this->plugin->evaluate($code);
	}
	public function getPlugin(){
		return $this->plugin;
	}
	public function testPermissionSilent(CommandSender $sender){
		return $sender instanceof ConsoleCommandSender;
	}
}
