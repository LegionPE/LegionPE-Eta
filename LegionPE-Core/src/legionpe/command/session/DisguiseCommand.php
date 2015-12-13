<?php

namespace legionpe\command\session;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class DisguiseCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("disguise", "Change your display name and nametags", "/dg <new name> (use \"|\" for line breaks, \"&\" for the color sign)", ["dg"]);
	}
	protected function run(Session $ses, array $args){
		$name = str_replace(["|", "&"], ["\n", "§"], implode(" ", $args));
		$ses->getPlayer()->setDisplayName($name);
		$ses->getPlayer()->setNameTag($name);
		return TextFormat::GREEN . "Your nametag has been changed to " . TextFormat::WHITE . $name . TextFormat::GREEN . ".";
	}
	protected function checkPerm(Session $ses){
		return $ses->isMod() and !$ses->isTrial();
	}
}
