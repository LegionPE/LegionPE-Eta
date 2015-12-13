<?php

namespace legionpe\command\warning;

use legionpe\command\session\SessionCommand;
use legionpe\LegionPE;
use legionpe\session\Session;
use legionpe\session\Warning;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class WarningCommand extends SessionCommand{
	/** @var string */
	private $shortName, $humanName;
	/** @var int */
	private $class;
	public function __construct(LegionPE $main, $shortName, $humanName, $class){
		$this->shortName = $shortName;
		$this->humanName = $humanName;
		$this->class = $class;
		parent::__construct($main);
	}
	protected function cinit(){
		Command::__construct("w" . $this->shortName, "Warn a player about " . $this->humanName, "/w$this->shortName <player> [-p <points>] <message ...>");
	}
	public function execute(CommandSender $sender, $lbl, array $args){
		if($sender instanceof Player){
			return parent::execute($sender, $lbl, $args);
		}
		$sender->sendMessage($this->dispatch($args, $sender->getName()));
		return true;
	}
	public function testPermissionSilent(CommandSender $sender){
		if($sender instanceof Player){
			return parent::testPermissionSilent($sender);
		}
		return true;
	}
	protected function run(Session $ses, array $args){
		return $this->dispatch($args, $ses->getRealName());
	}
	protected function checkPerm(Session $ses){
		return $ses->isMod();
	}
	public function dispatch(array $args, $issuer){
		if(!isset($args[1])){
			return TextFormat::RED . "Usage: " . $this->getUsage();
		}
		$target = $this->getSession(array_shift($args));
		if(!($target instanceof Session)){
			return TextFormat::RED . "There is no player online by that name.";
		}
		$points = Warning::$WARNINGS_POINTS[$this->class];
		if(isset($args[0])){
			if($args[0] === "-p"){
				array_shift($args);
				$points = array_shift($args);
			}
		}
		$msg = ucfirst(Warning::$WARNINGS_MESSAGES[$this->class]);
		if(isset($args[0])){
			$msg = implode(" ", $args);
		}
		$target->tell(TextFormat::GOLD . $msg);
		$target->tell(TextFormat::YELLOW . "You have been issued with $points warning points for that");
		$warning = $target->issueWarningEntry($this->class, $points, $msg, $issuer);
		return TextFormat::GREEN . "The following warning has been issued to $target:\n" . $warning->toString(TextFormat::AQUA);
	}
}
