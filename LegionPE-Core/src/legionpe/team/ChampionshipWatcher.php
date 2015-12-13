<?php

namespace legionpe\team;

use legionpe\LegionPE;
use legionpe\MysqlConnection;
use legionpe\session\Session;
use pocketmine\event\Timings;
use pocketmine\event\TimingsHandler;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;

class ChampionshipWatcher extends PluginTask{
	public function __construct(LegionPE $main){
		parent::__construct($main);
		$main->getServer()->getScheduler()->scheduleDelayedRepeatingTask($this, 1200, 1200);
	}
	public function onRun($t){
		/** @var LegionPE $main */
		$main = $this->owner;
		$conn = $main->getMySQLi();
		$time = $conn->nextChampionship();
		if($time < microtime(true)){
			$this->execChampionship();
			$conn->incrementNextChampionship();
		}
	}
	public function execChampionship(){
		$name = "Task: ChampionshipWatcher.Execution Runnable: " . __METHOD__ . " (interval: weekly)";
		if(!isset(Timings::$pluginTaskTimingMap[$name])){
			Timings::$pluginTaskTimingMap[$name] = new TimingsHandler($name, Timings::$schedulerSyncTimer);
		}
		$timings = Timings::$pluginTaskTimingMap[$name];
		$timings->startTiming();
		/** @var LegionPE $main */
		$main = $this->owner;
		$main->getServer()->broadcastMessage(TextFormat::AQUA . "The weekly Team Championship has ended! We are calculating the scores of the teams...");
		$main->updateGameSessionData();
		$teams = $main->getTeamManager()->getTeams();
		$points = array_map(function(Team $team){
			$info = $team->getStats();
			return $info->totalPoints() / $info->oldMembers;
		}, $teams);
		rsort($points);
		$main->getServer()->broadcastMessage(TextFormat::AQUA . "So here are the results!");
		$main->getServer()->broadcastMessage(TextFormat::AQUA . "Team " . $teams[array_keys($points)[0]] . " comes first with " . TextFormat::RED . $points[0] . TextFormat::AQUA . " points!");
		$main->getServer()->broadcastMessage(TextFormat::AQUA . "Team " . $teams[array_keys($points)[0]] . " comes second with " . TextFormat::RED . $points[0] . TextFormat::AQUA . " points!");
		$main->getServer()->broadcastMessage(TextFormat::AQUA . "Team " . $teams[array_keys($points)[0]] . " comes third with " . TextFormat::RED . $points[0] . TextFormat::AQUA . " points!");
		$main->getServer()->broadcastMessage(TextFormat::AQUA . "Old members of the above teams will receive coin boosts of 5%, 3% and 1% respectively!");
		for($i = 0; $i < 3; $i++){
			$factorTable = [0 => 1.05, 1 => 1.03, 2 => 1.01];
			$factor = $factorTable[$i];
			$members = $teams[array_keys($points)[$i]]->members;
			$off = [];
			foreach($members as $uid){
				if(($session = $main->getSessions()->getSessionByUID($uid)) instanceof Session){
					$session->setCoins($session->getCoins() * $factor);
					$session->tell(TextFormat::AQUA . "You have received %g coins!", $session->getCoins() * ($factor - 1));
				}
				else{
					$off[] = $uid;
				}
			}
			$uids = implode(" OR ", array_map(function($uid){
				return "uid=$uid";
			}, $off));
			$main->getMySQLi()->query("UPDATE players SET coins=coins*$factor WHERE $uids", MysqlConnection::RAW);
		}
		$timings->stopTiming();
	}
}
