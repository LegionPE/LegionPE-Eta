<?php

namespace legionpe\command\team;

use legionpe\command\sublib\SessionSubcommand;
use legionpe\MysqlConnection;
use legionpe\session\MysqlSession;
use legionpe\session\Session;
use legionpe\team\Team;
use pocketmine\utils\TextFormat;

class TeamMembersSubcommand extends SessionSubcommand{
	protected function onRun(Session $ses, array $args){
		if(isset($args[0])){
			$name = array_shift($args);
			$team = $this->main->getTeamManager()->getTeamByName($name);
		}
		else{
			$team = $ses->getTeam();
		}
		if(!($team instanceof Team)){
			return TextFormat::RED . "Usage: /team members [team name]";
		}
		$lightPurple = TextFormat::LIGHT_PURPLE;
		$darkBlue = TextFormat::DARK_BLUE;
		$ses->tell(TextFormat::DARK_BLUE . "Members in $lightPurple$team->name$darkBlue: (%d / %d)", $lightPurple . count($team->members) . $darkBlue, $lightPurple . $team->maxCapacity . $darkBlue);
		$members = array_fill_keys(array_keys(Team::$RANK_NAMES), []);
		$this->main->getTeamManager()->saveTeam($team);
		$sess = array_map(function(Session $session){
			return $session->getMysqlSession();
		}, $this->main->getTeamManager()->getSessionsOfTeam($team));
		if(count($sess) > 0){
			MysqlSession::saveData($sess, $this->main->getMySQLi());
		}
		$result = $this->main->getMySQLi()->query("SELECT names,teamrank FROM players WHERE tid=%d", MysqlConnection::ALL, $team->tid);
		foreach($result as $r){
			$members[(int) $r["teamrank"]][] = substr($r["names"], 0, -1);
		}
		foreach($members as $rank => $group){
			$ses->tell($lightPurple . Team::$RANK_NAMES[$rank] . $darkBlue . ": " . $lightPurple . implode(", ", $group) . $darkBlue);
		}
		return TextFormat::DARK_BLUE . "--- END OF MEMBERS LIST ---";
	}
	public function getName(){
		return "members";
	}
	public function getDescription(){
		return "List all members in a team";
	}
	public function getUsage(){
		return "/t mem [team name]";
	}
	public function getAliases(){
		return ["mem", "mems", "member"];
	}
}
