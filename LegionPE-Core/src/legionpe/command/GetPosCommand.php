<?php

namespace legionpe\command;

use legionpe\LegionPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;

class GetPosCommand extends Command implements PluginIdentifiableCommand{
	/**
	 * @var LegionPE
	 */
	private $plugin;
	public function __construct(LegionPE $plugin){
		$this->plugin = $plugin;
		parent::__construct("getpos", "Get player position", "/getpos [player (console only)]", ["gp"]);
	}
	public function execute(CommandSender $sender, $commandLabel, array $args){
		if($sender instanceof Player){
			$sender->sendMessage("You are at $sender->x,$sender->y,$sender->z;$sender->yaw,$sender->pitch@{$sender->getLevel()->getName()}");
		}
		else{
			if(!isset($args[0])){
				$sender->sendMessage("Usage: /getpos <player>");
				return true;
			}
			$player = $this->getPlugin()->getServer()->getPlayer($args[0]);
			if($player instanceof Player){
				$sender->sendMessage($player->getName() . " is at $player->x,$player->y,$player->z;$player->yaw,$player->pitch@{$player->getLevel()->getName()}");
			}
			else{
				$sender->sendMessage("Player $args[0] not found");
			}
		}
		return true;
	}
	public function getPlugin(){
		return $this->plugin;
	}
}