<?php

namespace legionpe\utils;

use legionpe\LegionPE;

class Statistics{
	private $stats = [];
	private $nextReport;
	public function __construct(array $titles, LegionPE $main){
		$this->nextReport = StatsReportMinuteTask::hourOfDay() + 1;
		foreach($titles as $t){
			$this->stats[$t] = [];
			for($h = 0; $h < 24; $h++){
				$this->stats[$t][$h] = 0;
			}
		}
		$main->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new StatsReportMinuteTask($main, $this), 1200, 1200);
	}
	public function increment($title){
		$this->stats[$title][StatsReportMinuteTask::hourOfDay()]++;
	}
	public function report($hour){
		$result = [];
		foreach($this->stats as $t => $d){
			$result[$t] = $d[$hour];
			$this->stats[$t][$hour] = 0;
		}
		return $result;
	}
	public function shouldReport(){
		if($this->nextReport === StatsReportMinuteTask::hourOfDay() - 1){
			return $this->nextReport++;
		}
		return false;
	}
}
