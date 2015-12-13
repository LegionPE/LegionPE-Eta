<?php

namespace legionpe\command\sublib;

use legionpe\LegionPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class SubcommandMap extends Command implements PluginIdentifiableCommand{
	private $main;
	/** @var Subcommand[] */
	private $subs = [];
	public function __construct(LegionPE $main, $name, $desc, $usage, $aliases = [], $subs = []){
		$this->main = $main;
		parent::__construct($name, $desc, $usage, is_array($aliases) ? $aliases : [$aliases]);
		$this->registerAll($subs);
	}
	public function registerSubcmd(Subcommand $subcmd){
		$this->subs[$subcmd->getName()] = $subcmd;
		$aliases = $subcmd->getAliases();
		foreach($aliases as $alias){
			$this->subs[$alias] = $subcmd;
		}
		$subcmd->map = $this;
		$subcmd->main = $this->main;
	}
	/**
	 * @param Subcommand[] $subs
	 */
	public function registerAll(array $subs){
		foreach($subs as $sub){
			$this->registerSubcmd($sub);
		}
	}
	public function execute(CommandSender $sender, $l, array $args){
		if(!isset($args[0]) or $args[0] === "help"){
			$page = 1;
			$lines = $sender instanceof Player ? 5 : count($this->subs); // not PHP_EOL, or it will be slower
			if(isset($args[1])){
				$page = (int) $args[1];
			}
			if(isset($args[2])){
				$lines = (int) $args[2];
			}
			$sender->sendMessage($this->displayHelp($sender, $page, $lines, $sender instanceof ConsoleCommandSender ? PHP_EOL:"\n"));
			$sender->sendMessage("Use /help <page> <lines> to show more");
			return true;
		}
		if(isset($this->subs[$sub = strtolower(array_shift($args))])){
			if($this->subs[$sub]->hasPerm($sender)){
				$sender->sendMessage($this->subs[$sub]->run($sender, $args));
				return true;
			}
			$sender->sendMessage($this->subs[$sub]->getPermissionMessage());
			return false;
		}
		$sender->sendMessage($this->displayHelp($sender, 1, 5, $sender instanceof ConsoleCommandSender ? PHP_EOL:"\n"));
		return false;
	}
	public function getPlugin(){
		return $this->main;
	}
	public function displayHelp(CommandSender $sender, $page = 1, $lines = 5, $eol = "\n"){
		$subs = array_filter($this->subs, function(Subcommand $sub) use($sender){
			return $sub->hasPerm($sender);
		});
		$closure = function(Subcommand $sub){
			return TextFormat::GRAY . "/" . $this->getName() . " " . $sub->getName() . TextFormat::WHITE . ": " . TextFormat::DARK_GREEN . $sub->getDescription() . TextFormat::WHITE . " - " . TextFormat::AQUA . $sub->getUsage();
		};
		$realPage = $page - 1;
		$max = ceil(count($subs) / $lines);
		$out = TextFormat::WHITE . "Showing help page " . TextFormat::RED . $page . TextFormat::WHITE . " of " . TextFormat::RED . $max . TextFormat::WHITE . ":";
		$init = $realPage * $lines;
		$fin = min($page * $lines, count($subs));
		for($i = $init; $i < $fin and isset($subs[$i]); $i++){
			$out .= $eol;
			$out .= $closure($subs[$i]);
		}
		return $out;
	}
}
