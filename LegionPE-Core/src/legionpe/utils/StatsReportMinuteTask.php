<?php

namespace legionpe\utils;

use legionpe\LegionPE;
use legionpe\MysqlConnection;
use pocketmine\scheduler\PluginTask;

class StatsReportMinuteTask extends PluginTask{
	/** @var Statistics */
	private $stats;
	public function __construct(LegionPE $main, Statistics $stats){
		parent::__construct($main);
		$this->stats = $stats;
	}
	public function onRun($ticks){
		if(($hour = $this->stats->shouldReport()) !== false){
			$data = $this->stats->report($hour);
			foreach($data as $title => $stat){
				/** @var LegionPE $main */
				$main = $this->getOwner();
				$conn = $main->getMysqli();
				$conn->query("UPDATE stats SET average=(average*total+$stat)/(total+1),total=total+1 WHERE title=%s;", MysqlConnection::RAW, $title);
			}
		}
	}
	public static function hourOfDay(){
		$hour = ((int) (time() / 3600)) % 24;
		while($hour < 0){
			$hour += 24;
		}
		return $hour;
	}
}
