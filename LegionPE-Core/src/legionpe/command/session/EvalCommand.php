<?php

namespace legionpe\command\session;

use legionpe\config\Settings;
use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class EvalCommand extends SessionCommand{
	protected function cinit(){
		Command::__construct("eval", "Evaluate command code in the context of legionpe\\LegionPE", "/eval <raw code>");
		$this->setPermissionMessage(sprintf("You must have the developer permission (0x%x) to use this command.", Settings::RANK_PERM_DEV));
	}
	protected function run(Session $ses, array $args){
		$code = implode(" ", $args);
		$this->getPlugin()->getLogger()->alert("$ses is executing PHP code on legionpe\\LegionPE context:" . PHP_EOL . TextFormat::GREEN . $code);
		$this->getPlugin()->evaluate($code);
		return "Executed code: $code";
	}
	public function checkPerm(Session $ses){
		return ($ses->getRank() & Settings::RANK_PERM_DEV) === Settings::RANK_PERM_DEV;
	}
}
