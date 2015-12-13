<?php

namespace legionpe\command;

use legionpe\LegionPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\level\Position;
use pocketmine\Player;

class DirectTpCommand extends Command implements PluginIdentifiableCommand{
	private $main;
	public function __construct(LegionPE $main){
		$this->main = $main;
		parent::__construct("dtp", "Direct teleport", "/dtp [from] <to> [distance behind <to>]");
		$this->setPermission("pocketmine.command.teleport");
	}
	public function execute(CommandSender $sender, $commandLabel, array $args){
		$dist = 0;
		if(count($args) === 0){
			$sender->sendMessage("Usage: /dtp [from] <to>[-<distance behind <to>>]");
			return;
		}
		elseif(count($args) === 1){
			if(!(($from = $sender) instanceof Player)){
				$sender->sendMessage("Please run this command in-game.");
				return;
			}
			$toName = $args[0];
			$pos = strpos($toName, "-");
			if($pos !== false){
				$dist = (int) substr($toName, $pos + 1);
				$toName = substr($toName, 0, $pos);
			}
			$to = $sender->getServer()->getPlayer($toName);
			if(!($to instanceof Player)){
				$sender->sendMessage("$toName isn't online!");
				return;
			}
		}else{
			$from = $sender->getServer()->getPlayer($args[0]);
			if(!($from instanceof Player)){
				$sender->sendMessage("$args[0] isn't online!");
				return;
			}
			$toName = $args[1];
			$pos = strpos($toName, "-");
			if($pos !== false){
				$dist = (int) substr($toName, $pos + 1);
				$toName = substr($toName, 0, $pos);
			}
			$to = $sender->getServer()->getPlayer($toName);
			if(!($to instanceof Player)){
				$sender->sendMessage("$toName isn't online!");
				return;
			}
		}
		$l = $to->getLevel();
		$v3 = $to->subtract($to->getDirectionVector()->multiply($dist))->floor();
		for($i = $v3->y; $i < 128; $v3->y = (++$i)){
			$b = $l->getBlock($v3);
			$id = $b->getId();
			if($id === 0 or 8 <= $id and $id <= 11){
				break;
			}
		}
		$from->teleport(Position::fromObject($v3, $l));;
		$from->sendMessage("Teleported to {$to->getName()}");
	}
	/**
	 * @return \pocketmine\plugin\Plugin
	 */
	public function getPlugin(){
		return $this->main;
	}
}
