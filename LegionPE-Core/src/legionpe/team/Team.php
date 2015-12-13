<?php

namespace legionpe\team;

use legionpe\chat\Channel;
use legionpe\chat\ChannelSubscriber;
use legionpe\LegionPE;
use legionpe\MysqlConnection;
use legionpe\session\Session;

class Team implements ChannelSubscriber{
	const RANK_LEADER = 4;
	const RANK_CO_LEADER = 3;
	const RANK_SENIOR = 2;
	const RANK_MEMBER = 1;
	const RANK_JUNIOR = 0;
	/** @var string[] */
	public static $RANK_NAMES = [
		self::RANK_LEADER => "Leader",
		self::RANK_CO_LEADER => "Co-Leader",
		self::RANK_SENIOR => "Senior-Member",
		self::RANK_MEMBER => "Member",
		self::RANK_JUNIOR => "Junior-Member"
	];
	/** @var LegionPE */
	private $main;
	/** @var int */
	public $tid;
	/** @var string */
	public $name;
	/** @var int */
	public $maxCapacity = 12;
	/** @var bool */
	public $open;
	/** @var int[] */
	public $members = [];
	/** @var bool[] */
	public $invited = [];
	/** @var string */
	public $requires, $rules;
	/** @var Channel */
	private $chan;
	public function __construct(LegionPE $main, $tid, $name, $maxCapacity, $isOpen = true, $members = [], $invited = "", $requires = "", $rules = ""){
		$this->main = $main;
		$this->tid = $tid;
		$this->name = $name;
		$this->maxCapacity = $maxCapacity;
		$this->open = $isOpen;
		$this->members = $members;
		$this->invited = $invited === "" ? [] : array_fill_keys(array_map("intval", explode(",", $invited)), true);
		$this->requires = $requires;
		$this->rules = $rules;
		$this->chan = $main->getChannelManager()->joinChannel($this, "team_$this", Channel::CLASS_TEAM);
	}
	public function join(Session $session, $rank = self::RANK_JUNIOR){
		if(isset($this->members[$session->getUID()])){
			return false;
		}
		$this->members[$session->getUID()] = $rank;
		$session->getMysqlSession()->data["tid"] = $this->tid;
		$session->getMysqlSession()->data["teamrank"] = $rank;
		$session->getMysqlSession()->data["teamjointime"] = time();
		return true;
	}
	public function quit(Session $session){
		if(!isset($this->members[$session->getUID()])){
			return false;
		}
		unset($this->members[$session->getUID()]);
		$session->getMysqlSession()->data["tid"] = -1;
		$session->getMysqlSession()->data["teamrank"] = 0;
		$session->getMysqlSession()->data["teamjointime"] = 0;
		return true;
	}
	public function invite(Session $session){
		if(isset($this->invited[$session->getUID()])){
			return false;
		}
		$this->invited[$session->getUID()] = true;
		return true;
	}
	public function uninvite(Session $session){
		if(!isset($this->invited[$session->getUID()])){
			return false;
		}
		unset($this->invited[$session->getUID()]);
		return true;
	}
	public function canJoin(Session $session){
		$a = count($this->members) < $this->maxCapacity;
		$b = $this->open;
		$c = isset($this->invited[$session->getUID()]);
		return $a and ($b or $c);
	}
	public function isFull(){
		return count($this->members) >= $this->maxCapacity;
	}
	public function __toString(){
		return $this->name;
	}
	public function isDeafTo(ChannelSubscriber $other){
		return true;
	}
	public function isVerboseSub(){
		return false;
	}
	public function isMuted(){
		return false;
	}
	public function mute($seconds){
		// N/A
	}
	public function unmute(){
		// N/A
	}
	public function subscribeToChannel(Channel $channel){
		$channel->subscribe($this);
	}
	public function unsubscribeFromChannel(Channel $channel){
		$channel->unsubscribe($this);
	}
	public function getID(){
		return "Team: $this";
	}
	public function tell($msg, ...$args){
		$msg = sprintf($msg, ...$args);
		foreach($this->getMain()->getTeamManager()->getSessionsOfTeam($this) as $s){
			$s->tell($msg);
		}
	}
	public function isOper(){
		return false;
	}
	public function getMain(){
		return $this->main;
	}
	public function getSessionsOnline(){
		return $this->getMain()->getTeamManager()->getSessionsOfTeam($this);
	}
	public function formatChat($msg, $isACTION = false){
		return $isACTION ? "* Team:$this $msg":"[Team:$this] $msg";
	}
	/**
	 * @return Channel
	 */
	public function getChannel(){
		return $this->chan;
	}
	public function getStats($new = 604800){
		$info = new TeamInfo;
		$data = $this->main->getMySQLi()->query("SELECT COUNT(*)AS cnt,SUM((SELECT kills FROM kitpvp WHERE uid=players.uid))AS kills,SUM((SELECT deaths FROM kitpvp WHERE uid=players.uid))AS deaths,MAX((SELECT maxstreak FROM kitpvp WHERE uid=players.uid))AS pvpmaxstreak,SUM((SELECT completions FROM parkour WHERE uid=players.uid))AS completions,SUM((SELECT falls FROM parkour WHERE uid=players.uid))AS falls,SUM((SELECT tmpfalls FROM parkour WHERE uid=players.uid))AS tmpfalls,SUM((SELECT wins FROM spleef WHERE uid=players.uid))AS wins,SUM((SELECT losses FROM spleef WHERE uid=players.uid))AS losses,SUM((SELECT draws FROM spleef WHERE uid=players.uid))AS draws,MAX((SELECT maxstreak FROM spleef WHERE uid=players.uid))AS spleefmaxstreak FROM players WHERE tid=$this->tid AND teamjointime<=unix_timestamp()-$new", MysqlConnection::ASSOC);
		$info->oldMembers = $data["cnt"];
		$info->totalMembers = count($this->members);
		$info->pvpKills = $data["kills"];
		$info->pvpDeaths = $data["deaths"];
		$info->pvpMaxStreak = $data["pvpmaxstreak"];
		$info->parkourWins = $data["completions"];
		$info->parkourFalls = $data["falls"];
		$info->parkourTmpFalls = $data["tmpfalls"];
		$info->spleefWins = $data["wins"];
		$info->spleefLosses = $data["losses"];
		$info->spleefDraws = $data["draws"];
		$info->spleefMaxStreak = $data["spleefmaxstreak"];
		return $info;
	}
}
