<?php

namespace legionpe\team;

class TeamInfo{
	public $oldMembers;
	public $totalMembers;
	public $pvpKills;
	public $pvpDeaths;
	public $pvpMaxStreak;
	public $parkourWins;
	public $parkourFalls;
	public $parkourTmpFalls;
	public function parkourAvgFalls(){
		return round($this->parkourWins > 0 ? (($this->parkourFalls - $this->parkourTmpFalls) / $this->parkourWins) : 0, 3);
	}
	public $spleefWins;
	public $spleefLosses;
	public $spleefDraws;
	public $spleefMaxStreak;

	public function totalPoints(){
		return 0
			+ $this->pvpKills * 8
			- $this->pvpDeaths * 5
			+ $this->pvpMaxStreak * 30
			+ $this->parkourWins * 64
			- $this->parkourAvgFalls() * 4
			+ $this->spleefWins * 50
			+ $this->spleefDraws * 10
			- $this->spleefLosses * 10
			+ $this->spleefMaxStreak * 100
			;
	}
}
