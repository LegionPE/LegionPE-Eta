<?php

namespace legionpe\session;

use legionpe\utils\MUtils;
use pocketmine\utils\TextFormat;

class MuteIssue{
	public $reason;
	public $issuer;
	public $target;
	public $from;
	public $duration;
	public $till;
	public function notify($ipOf, $normColor = TextFormat::AQUA){
		return $normColor . "IP " . TextFormat::LIGHT_PURPLE . $this->target . $normColor . " (IP of $ipOf) has been muted by " . TextFormat::LIGHT_PURPLE . $this->issuer . $normColor . " for a period of " . TextFormat::LIGHT_PURPLE . $this->duration . " " . MUtils::time_secsToString(time() - $this->from) . " ago" . $normColor . " for reason: " . TextFormat::LIGHT_PURPLE . $this->reason . "$normColor.";
	}
}
