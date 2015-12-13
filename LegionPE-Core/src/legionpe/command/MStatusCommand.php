<?php

namespace legionpe\command;

use legionpe\LegionPE;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\StatusCommand;

class MStatusCommand extends StatusCommand{
	private $main;
	public function __construct(LegionPE $main){
		$this->main = $main;
		parent::__construct("mstatus");
	}
	public function execute(CommandSender $sender, $lbl, array $args){
		parent::execute($sender, $lbl, $args);
		if($this->testPermissionSilent($sender)){
			$sender->sendMessage(sprintf("MySQL ping result: %s in %f milliseconds", $this->main->getMySQLi()->measurePing($micro) ? "success":"failure", $micro * 1000));
		}
	}
}
