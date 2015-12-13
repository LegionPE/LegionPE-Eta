<?php

namespace legionpe\command;

use legionpe\LegionPE;
use legionpe\session\MuteIssue;
use legionpe\session\Session;
use legionpe\utils\MUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class MuteCommand extends Command implements PluginIdentifiableCommand{
	private $main;
	public function __construct(LegionPE $main){
		$this->main = $main;
		parent::__construct("mute", "Mute an online player by his/her IP", "/mute <player> <minutes> <message ...>");
	}
	public function execute(CommandSender $sender, $lbl, array $args){
		if($sender instanceof Player){
			if(!$this->getPlugin()->getSessions()->getSession($sender)->isMod()){
				$sender->sendMessage($this->getPermissionMessage());
				return true;
			}
		}
		if(!isset($args[2])){
			return false;
		}
		$target = $this->getPlugin()->getSessions()->getSession($name = array_shift($args));
		if(!($target instanceof Session)){
			$sender->sendMessage(TextFormat::RED . "Cannot find player \"$name\".");
			return true;
		}
		$duration = (int) (((float) array_shift($args)) * 60);
		if($duration === 0){
			return false;
		}
		$issue = new MuteIssue();
		$issue->issuer = $ip = $sender->getName();
		$issue->target = $target->getPlayer()->getAddress();
		$issue->reason = implode(" ", $args);
		$issue->from = time();
		$issue->duration = MUtils::time_secsToString($duration);
		$issue->till = time() + $duration;
		$target->tell(TextFormat::BLACK . str_repeat("~", 20));
		$target->tell($issue->notify("you", TextFormat::YELLOW));
		$target->tell(TextFormat::BLACK . str_repeat("~", 20));
		$this->getPlugin()->getServer()->broadcast($issue->notify($target->getRealName(), TextFormat::DARK_GREEN), Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
		$this->getPlugin()->getSessions()->mutedIps[$ip] = $issue;
		return true;
	}
	public function testPermissionSilent(CommandSender $sender){
		return !($sender instanceof Player) or $this->getPlugin()->getSessions()->getSession($sender)->isMod();
	}
	public function getPlugin(){
		return $this->main;
	}
}
