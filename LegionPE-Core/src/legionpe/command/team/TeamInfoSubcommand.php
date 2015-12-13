<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamInfoSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(isset($args[0])){
			$team = $this->main->getTeamManager()->getTeamByName($args[0]);
		}
		elseif(!(($team = $ses->getTeam()) instanceof Team)){
			return TextFormat::RED . "You are not in a team! Usage: " . $this->getUsage();
		}
		$this->main->updateGameSessionData();
		$info = $team->getStats();
		$new = $info->totalMembers - $info->oldMembers;
		$ses->tell(TextFormat::DARK_BLUE . "%s team $team ($info->totalMembers / $team->maxCapacity members%s)", $team->open ? "Open":"Invite-only", $new > 0 ? ($new > 1 ? ", $new are new" : ", 1 is new") : "");
		$ses->tell("Requirements to join the team: ");
		foreach(explode("\n", $team->requires) as $line){
			$ses->tell(TextFormat::RESET . $line);
		}
		$ses->tell("Team rules:");
		foreach(explode("\n", $team->rules) as $line){
			$ses->tell(TextFormat::RESET . $line);
		}
		$gold = TextFormat::GOLD;
		$dg = TextFormat::DARK_GREEN;
		$kd = ($info->pvpDeaths > 0) ? ((string) round($info->pvpKills / $info->pvpDeaths, 3)) : "N/A";
		$ses->tell($gold . "KitPvP:$dg $info->pvpKills kills, $info->pvpDeaths deaths, max killstreak $info->pvpMaxStreak, Overall K/D $kd");
		$ses->tell($gold . "Parkour:$dg $info->parkourWins completions, average {$info->parkourAvgFalls()} falls per completion");
		$ses->tell($gold . "Spleef:$dg $info->spleefWins wins, $info->spleefLosses losses, $info->spleefDraws draws");
		$ses->tell($gold . "Overall team points:$dg " . round($info->totalPoints() / $info->oldMembers, 3));
		return null;
	}
	public function getName(){
		return "info";
	}
	public function getDescription(){
		return "View team info";
	}
	public function getUsage(){
		return "/t i [team]";
	}
	public function getAliases(){
		return ["i"];
	}
}
