<?php

namespace legionpe\command;

use legionpe\LegionPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class UnmuteCommand extends Command implements PluginIdentifiableCommand{
	private $main;
	public function __construct(LegionPE $main){
		$this->main = $main;
		parent::__construct("unmute", "Unmute a player", "/unmute <player> <message ...>");
	}
	public function execute(CommandSender $sender, $label, array $args){
		if(!isset($args[0])){
			return false;
		}
		$player = $this->getPlugin()->getServer()->getPlayer(array_shift($args));
		if(!($player instanceof Player)){
			$sender->sendMessage("That player isn't online");
			return true;
		}
		if(isset($this->getPlugin()->getSessions()->mutedIps[$ip = $player->getAddress()])){
			unset($this->getPlugin()->getSessions()->mutedIps[$ip]);
			$player->sendMessage(implode(" ", $args));
			$sender->sendMessage(TextFormat::GREEN . "IP $ip has been unmuted.");
		}
		else{
			$sender->sendMessage(TextFormat::RED . "That IP isn't muted.");
		}
		return true;
	}
	public function getPlugin(){
		return $this->main;
	}
}
