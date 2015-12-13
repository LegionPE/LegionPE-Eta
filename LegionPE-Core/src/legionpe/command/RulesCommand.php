<?php

namespace legionpe\command;

use legionpe\LegionPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;

class RulesCommand extends Command implements PluginIdentifiableCommand{
	/** @var LegionPE */
	private $plugin;
	public function __construct(LegionPE $plugin){
		$this->plugin = $plugin;
		parent::__construct("rules", "List of rules", "/rules [index|<page>]");
	}
	public function execute(CommandSender $sender, $lbl, array $args){
		if(!isset($args[0]) or strtolower($args[0]) === "index"){
			$sender->sendMessage("#1: No spamming");
			$sender->sendMessage("#2: No mods or glitches use.");
			$sender->sendMessage("#3: Use proper chat behavior");
			$sender->sendMessage("#4: Inappropriate usernames are disallowed.");
			$sender->sendMessage("Use /rules <rule number> to see the details and interpretation for each of them.");
			goto eos;
		}
		$page = intval($args[0]);
		switch($page){
			case 1:
				$sender->sendMessage("#1 of 7: No spamming.");
				$sender->sendMessage("If several meaningless lines are sent, it will be considered as spamming.");
				$sender->sendMessage("Small-scale spams will lead to mutes.");
				$sender->sendMessage("Large-scale spams will lead to an IP ban.");
				break;
			case 2:
				$sender->sendMessage("#2 / 7: No mods/glitches");
				$sender->sendMessage("Only unmodified MCPE copies downloaded from official source are allowed to be used to connect to this server, unless otherwise approved by staffs.");
				$sender->sendMessage("You should report server-related bugs/glitches to staffs as soon as possible and do not use them as your advantage.");
				$sender->sendMessage("Glitches/bugs include pushing players off spawn.");
				$sender->sendMessage("Offenders of this rule are temp-banned or kicked.");
				break;
			case 3:
				$sender->sendMessage("#3 / 7: Use proper chat behaviour.");
				$sender->sendMessage("This includes:");
				$sender->sendMessage("1. No swearing. You will be muted for 30 minutes if you do so.");
				$sender->sendMessage("2. Offensive chat is disallowed. Offensive chat includes but is not limited to threatening or blackmailing others.");
				$sender->sendMessage("3. Abusive caps disturb players and are hard to read. You will be muted for 5 minutes if you do so.");
				$sender->sendMessage("4. Advertizing other servers or YouTube channels is disallowed.");
				break;
			case 4:
				$sender->sendMessage("#4 / 7: Inappropriate usernames are disallowed.");
				$sender->sendMessage("They will be banned on sight.");
				break;
			default:
				$sender->sendMessage("No such page (sorry, p. $page is not finished)!");
				return true;
		}
		eos:
		$sender->sendMessage("If the rules above are offended repeatedly, the penalty may increment.");
		$sender->sendMessage("Don't hesitate to contact a staff member if you have a question!");
		return true;
	}
	/**
	 * @return LegionPE
	 */
	public function getPlugin(){
		return $this->plugin;
	}
}
