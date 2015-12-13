<?php

namespace legionpe\session;

use legionpe\chat\Channel;
use legionpe\chat\ChannelSubscriber;
use legionpe\config\Settings;
use legionpe\games\Game;
use legionpe\LegionPE;
use legionpe\MysqlConnection;
use legionpe\team\Team;
use legionpe\utils\CallbackPluginTask;
use legionpe\utils\MUtils;
use pocketmine\block\Block;
use pocketmine\block\Trapdoor;
use pocketmine\command\Command;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

define("ZERO_HASH", str_repeat("0", 128));

class Session extends PluginTask implements SessionEvents, ChannelSubscriber{
	const SALT = "NaCl"; // lol sodium chloride - table salt
	const UNAUTHENTICATED_TAG = TextFormat::RED . "{UNAUTHENTICATED}" . TextFormat::WHITE;

	const IPCONFIG_DISABLE = 0;
	const IPCONFIG_LASTIP = 1;
//	const IPCONFIG_ANYIP = 2;

	const SESSION_INIT          = 0;
	const SESSION_GAME_HUB      = 0b01000001;
	const SESSION_GAME_KITPVP   = 0b01000010;
	const SESSION_GAME_PARKOUR  = 0b01000011;
	const SESSION_GAME_SPLEEF   = 0b01000100;
	const SESSION_GAME_INFECTED = 0b01000101;
	const SESSION_LOGIN         = 0b00100000;
	const SESSION_LOGIN_MAX     = 0b00100101;
	const SESSION_REG_INTRO     = 0b00010000;
	const SESSION_REG_REP       = 0b00010001;
	const SESSION_REG_IP        = 0b00010010;
	const SES_STATE_REG         = 0b00010000;
	const SES_STATE_LOGIN       = 0b00100000;
	const SES_STATE_GAME        = 0b01000000;

	const INV_NORMAL_ACCESS = 0;
	const INV_CHOOSE_GAME = 1;

	const INVISIBLE_UNAUTH = 0;
	const INVISIBLE_SPAWN = 1;
	const INVISIBLE_COMMAND = 2;

	public static $RANDOM_BROADCASTS = [
		"If you can't see somebody, use /sa.",
		"Use /coins to view your coins! You can use them in the shops.",
		"Use /quit to go back to hub!",
		"Use `/chat off` to turn off chat from everyone! (chat will be turned back on when you rejoin)",
		"Use `/ignore|unignore <player>` to ignore/unignore players!",
		"Use `/tpr`, `/tpa`, `/tpd` and `/tpc` to handle teleport requests (KitPvP only)!",
		"Use /stats to view your stats!",
		"Tired of your friends accusing you killing them? Use /friend. (KitPvP only)",
		"Use /restart to reset your parkour progress and teleport back to parkour spawn!",
		"More minigames are coming soon!",
		"Please report any bugs by tweeting @PEMapModder on Twitter",
		"Changed your mind and wanna enable/disable IP auth? No problem, run `/auth ip yes|no`.",
		"Use /rules to check our server rules!",
		"Players blocking your way? /hide them!",
		"Do `/team create` to create your own team!",
		"Want to get more friends? Visit http://legionpvp.eu to donate.",
		"Want to have access to buying better items? Visit http://legionpvp.eu to donate.",
		"Want to create your own team? Visit http://legionpvp.eu to donate.",
		"Want to earn coins faster? Visit http://legionpvp.eu to donate.",
		"Use `/ch t` to switch to your team channel, `/ch g` to go back!",
		"Use `/chat off <channel>` to ignore chat messages from #<channel>!",
		"NEVER give ANYone including staff members your password; we don't know your password and we won't need it.",
		"NEVER give ANYone including staff members your password; we don't know your password and we won't need it.",
		"NEVER give ANYone including staff members your password; we don't know your password and we won't need it.",
	];

	/** @var SessionInterface */
	private $sesList;
	/** @var \legionpe\LegionPE */
	private $main;
	/** @var Player */
	private $player;
	/** @var MysqlSession */
	private $mysqlSession;
	/** @var SpamDetector */
	private $spamDetector;
	/** @var int */
	private $session = self::SESSION_INIT;
	/** @var string[128] */
	private $tmpPswd;
	/** @var bool */
	public $wannaGrind = false;
	/** @var int */
	private $uid = -1;
	/** @var int */
	public $joinTime, $lastActivity;
	public $invSession = self::INV_NORMAL_ACCESS;
	/** @var Position */
	private $spawningPosition;
	/** @var \pocketmine\permission\PermissionAttachment */
	private $perm;
	/** @var bool */
	public $authenticated = false, $kicked = false, $compassOn = false, $quitTeamConfirmed = false, $queryReminded;
	private  $invisibilityFactors = [], $invisibleTo = [];

	/** @var Channel */
	private $writeToChannel, $personalChannel;
	/** @var string[] */
	private $deafToChannels = [];
	/** @var bool */
	private $deaf = false;
	/** @var bool */
	private $verboseSub = false;

	/** @var string */
	public $ignoring = ","; // always surround them with commas
	private $disableTpListener = false;

	public function auth($method){
		$this->authenticated = true;
		$this->getPlayer()->setDisplayName(substr($this->getPlayer()->getDisplayName(), strlen(self::UNAUTHENTICATED_TAG)));
		$teamStr = "";
		if(($team = $this->getTeam()) instanceof Team){
			$teamrank = Team::$RANK_NAMES[$this->getMysqlSession()->data["teamrank"]];
			$teamStr = "Team " . TextFormat::AQUA . $team->name . "\n" . TextFormat::LIGHT_PURPLE . $teamrank . TextFormat::WHITE . "\n";
		}
		$this->getPlayer()->setNameTag($teamStr . $this->getPlayer()->getNameTag());
		if($method){
			$this->tell("You have been authenticated by $method.");
		}
		$this->tell("Welcome to LegionPE.");
		$this->main->getStats()->increment(LegionPE::TITLE_LEGIONPE_JOINS);
		$this->subscribeToChannel($this->getMain()->getMandatoryChannel());
		$this->perm->setPermission("pocketmine.command.list", true);
		$isMod = ($this->getRank() & Settings::RANK_PERM_MOD) === Settings::RANK_PERM_MOD;
		$isAdmin = ($this->getRank() & Settings::RANK_PERM_ADMIN) === Settings::RANK_PERM_ADMIN;
		if($isMod){
			$this->subscribeToChannel($this->getMain()->getSupportChannel());
			$this->subscribeToChannel($this->getMain()->getStaffChannel());
		}
		$this->perm->setPermission("pocketmine.command.say", $isMod);
		$this->perm->setPermission("pocketmine.command.gamemode", $isMod);
		$this->perm->setPermission("pocketmine.command.status", $isMod);
		$this->perm->setPermission("pocketmine.command.teleport", $isMod);
		$this->perm->setPermission("pocketmine.command.time", $isMod);
		$this->perm->setPermission(Server::BROADCAST_CHANNEL_ADMINISTRATIVE, $isMod);
		$this->perm->setPermission("pocketmine.command.give", $isAdmin);
		$this->perm->setPermission("pocketmine.command.reload", $isAdmin);
		$this->perm->setPermission("pocketmine.command.stop", $isAdmin);
		$this->perm->setPermission("specter.command", $isAdmin);
		$this->session = self::SESSION_GAME_HUB;
		$this->disableTpListener = true;
		$this->teleport($this->spawningPosition);
		$this->disableTpListener = false;
		$team = $this->getTeam();
		if($team instanceof Team){
			$this->subscribeToChannel($team->getChannel());
		}
		$game = Settings::getGameByLevel($l = $this->spawningPosition->getLevel(), $this->main);
		if($game instanceof Game){
			$this->joinGame($game);
		}
		else{
			$this->onHub();
		}
		$this->setVisible(self::INVISIBLE_UNAUTH);
		$this->mysqlSession->data["lastonline"] = time();
		$this->mysqlSession->data["lastip"] = $this->getPlayer()->getAddress();
		$ips = explode(",", $this->mysqlSession->data["histip"]);
		if(!in_array($this->getPlayer()->getAddress(), $ips)){
			$ips[] = $this->getPlayer()->getAddress();
			$this->mysqlSession->data["histip"] = implode(",", $ips);
		}
		$this->tell("Limited 25% discount on all ranks ending on Friday! View them here: www.legionpvp.eu");
	}
	public function tell($string, ...$args){
		if(func_num_args() === 1){
			$this->getPlayer()->sendMessage($string); // faster
		}
		else{
			$this->getPlayer()->sendMessage(sprintf($string, ...$args));
		}
	}
	public function sendPagedText($text, $page, $linesPerPage){
		$lines = array_map("trim", explode("\n", $text));
		$max = ceil(count($lines) / $linesPerPage);
		$page = max(1, min($max, $page));
		$start = ($page - 1) * $linesPerPage;
		$this->tell("Page $page / $max:");
		for($i = 0; $i < $linesPerPage; $i++){
			if(isset($lines[$start + $i])){
				$this->tell($lines[$start + $i]);
			}
			else{
				break;
			}
		}
	}
	public function sendRandomBroadcast(){
		$this->tell(TextFormat::AQUA . Settings::getRandomBroadcast());
	}
	public function teleport(Position $pos){
		$this->disableTpListener = true;
		if($this->getPlayer() instanceof Player){
			$yaw = null;
			$pitch = null;
			if($pos instanceof Location){
				$yaw = $pos->yaw;
				$pitch = $pos->pitch;
			}
			$this->getPlayer()->teleport($pos, $yaw, $pitch);
		}
		$this->disableTpListener = false;
	}
	public function kick($msg, $timeout = 40, $silent = false){
		$this->kicked = true;
		if(!$silent){
			$this->tell("You have been kicked: $msg");
		}
		$player = $this->getPlayer();
		$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(
			new CallbackPluginTask($this->getMain(), function() use($player, $msg){
				$player->close($msg, $msg);
			}
			), $timeout);
		$this->getPlayer()->blocked = true;
	}
	public function joinGame(Game $game){
		$this->getPlayer()->getInventory()->clearAll();
		if(!$game->onJoin($this)){
			return false;
		}
		$this->switchSession($game->getSessionId());
		return true;
	}
	public function showToAll(){
		$this->getPlayer()->spawnToAll();
	}
	public function warnAds(Session $issuer, $msg = "Advertizing is not allowed"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("ads", $issuer, $msg)){
			case 1:
				$this->mute(900);
				$this->tell("You have been muted for 15 minutes for that.");
				break;
			case 2:
				$this->mute(1800);
				$this->tell("You have been muted for 30 minutes for that. This is your second last chance.");
				break;
			case 3:
				$this->mute(3600);
				$this->tell("You have been muted for one hour for that. This is your last chance. Next time will be a ban.");
				break;
			case 4:
				$this->tell("You have been banned for 1 day for advertizing after 3 times of previous warning.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 86400);
				break;
			case 5:
				$this->tell("You have been banned for 3 days for using advertizing despite 4 warnings given previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 259200);
				break;
			default:
				$this->tell("You have been banned for 30 days for using advertizing despite many warnings given previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 2592000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnCaps(Session $issuer, $msg = "Abusive caps are not allowed"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("caps", $issuer, $msg)){
			case 1:
				$this->mute(300);
				$this->tell("You have been muted for 5 minutes for that.");
				break;
			case 2:
				$this->mute(900);
				$this->tell("You have been muted for 15 minutes for that. This is your second last chance.");
				break;
			case 3:
				$this->mute(3600);
				$this->tell("You have been muted for one hour for that. This is your last chance. Next time will be a ban.");
				break;
			case 4:
				$this->tell("You have been banned for 1 day for using abusive caps after 3 times of previous warning.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 86400);
				break;
			case 5:
				$this->tell("You have been banned for 3 days for using abusive caps despite 4 warnings given previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 259200);
				break;
			default:
				$this->tell("You have been banned for 30 days for using abusive caps despite many warnings given previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 2592000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnSwearing(Session $issuer, $msg = "Swearing or offensive words should not be used on LegionPE"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("swears", $issuer, $msg)){
			case 1:
				$this->mute(600);
				$this->tell("You have been muted for 10 minutes for that.");
				break;
			case 2:
				$this->mute(1800);
				$this->tell("You have been muted for 30 minutes for that. This is your second last chance.");
				break;
			case 3:
				$this->mute(3600);
				$this->tell("You have been muted for one hour for that. This is your last chance. Next time will be a ban.");
				break;
			case 4:
				$this->tell("You have been banned for 3 days for swearing after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 259200);
				break;
			case 5:
				$this->tell("You have been banned for one week for swearing after 4 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 604800);
				break;
			default:
				$this->tell("You have been banned for 30 days for swearing despite warnings issued previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 2592000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnSpamming(Session $issuer, $msg = "Spamming is not allowed on LegionPE"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("spams", $issuer, $msg)){
			case 1:
				$this->mute(1800);
				$this->tell("You have been muted for 30 minutes for that.");
				break;
			case 2:
				$this->mute(3600);
				$this->tell("You have been muted for one hour for that. This is your second last chance.");
				break;
			case 3:
				$this->mute(43200);
				$this->tell("You have been muted for 12 hours for that. This is your last chance. Next time will be a ban.");
				break;
			case 4:
				$this->tell("You have been banned for one week for spamming after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 604800);
				break;
			default:
				$this->tell("You have been banned for 30 days for spamming after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 2592000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnImpose(Session $issuer, $msg = "Imposing staffs is not allowed"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("impose", $issuer, $msg)){
			case 1:
				$this->mute(900);
				$this->tell("You have been muted for 15 minutes for that.");
				break;
			case 2:
				$this->mute(1800);
				$this->tell("You have been muted for 30 minutes for that. This is your second last chance.");
				break;
			case 3:
				$this->mute(3600);
				$this->tell("You have been muted for one hour for that. This is your last chance. Next time will be a ban.");
				break;
			case 4:
				$this->tell("You have been banned for 1 day for staff imposing after 3 times of previous warning.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 86400);
				break;
			case 5:
				$this->tell("You have been banned for 3 days for staff imposing despite 4 warnings given previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 259200);
				break;
			default:
				$this->tell("You have been banned for 30 days for staff imposing despite many warnings given previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 2592000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnChat(Session $issuer, $msg = "You are having inappropriate chat behaviour"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("chatwarns", $issuer, $msg)){
			case 1:
				$this->mute(1800);
				$this->tell("You have been muted for 30 minutes for that.");
				break;
			case 2:
				$this->mute(3600);
				$this->tell("You have been muted for one hour for that. This is your second last chance.");
				break;
			case 3:
				$this->mute(43200);
				$this->tell("You have been muted for 12 hours for that. This is your last chance. Next time will be a ban.");
				break;
			case 4:
				$this->tell("You have been banned for one week for inappropriate chat behaviour after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 604800);
				break;
			default:
				$this->tell("You have been banned for 30 days for inappropriate chat behaviour despite mutliple warnings issued previously.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 2592000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnFlying(Session $issuer, $msg = "Flying is disallowed on LegionPE"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("flies", $issuer, $msg)){
			case 1:
				$this->tell(TextFormat::RED . "You have been warned for using flying mods. Please disable them as soon as possible, or you will be banned.");
				break;
			case 2:
				$this->tell(TextFormat::RED . "You have been banned for 14 days for using flying mods.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 1209600);
				break;
			case 3:
				$this->tell(TextFormat::RED . "This is your third time getting warned for using flying mods. You have been banned for 45 days.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 3888000);
				break;
			default:
				$this->tell("You have been banned for 75 days for using sprinting mods after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 6480000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnSprinting(Session $issuer, $msg = "Sprinting is disallowed on LegionPE"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("sprints", $issuer, $msg)){
			case 1:
				$this->tell(TextFormat::RED . "You have been warned for using sprinting mods. Please disable them as soon as possible, or you will be banned.");
				break;
			case 2:
				$this->tell(TextFormat::RED . "You have been banned for 14 days for using sprinting mods.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 1209600);
				break;
			case 3:
				$this->tell(TextFormat::RED . "This is your third time getting warned for using sprinting mods. You have been banned for 45 days.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 3888000);
				break;
			default:
				$this->tell("You have been banned for 75 days for using sprinting mods after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 6480000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnGhostHack(Session $issuer, $msg = "Using mods (especially Ghost Hack) is disallowed on LegionPE"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("ghwarns", $issuer, $msg)){
			case 1:
				$this->tell(TextFormat::RED . "You have been warned for using Ghost Hack mod. Please disable it as soon as possible, or you will be banned.");
				break;
			case 2:
				$this->tell(TextFormat::RED . "You have been banned for 14 days for using Ghost Hack mod.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 1209600);
				break;
			case 3:
				$this->tell(TextFormat::RED . "This is your third time getting warned for using Ghost Hack mod. You have been banned for 45 days.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 3888000);
				break;
			default:
				$this->tell("You have been banned for 75 days for using Ghost Hack mod after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 6480000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnMods(Session $issuer, $msg = "Using mods is disallowed on LegionPE"){
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("modswarns", $issuer, $msg)){
			case 1:
				$this->tell(TextFormat::RED . "You have been warned for using mods. Please disable them as soon as possible, or you will be banned.");
				break;
			case 2:
				$this->tell(TextFormat::RED . "You have been banned for 14 days for using mods.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 1209600);
				break;
			case 3:
				$this->tell(TextFormat::RED . "This is your third time getting warned for using mods. You have been banned for 45 days.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 3888000);
				break;
			default:
				$this->tell("You have been banned for 75 days for using mods after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 6480000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	public function warnMisc(Session $issuer, $msg = null){
		if($msg === null){
			$msg = "unknown reason from $issuer";
		}
		$this->tell(str_repeat("!", 32));
		$this->tell(str_repeat("~", 32));
		switch($this->warn("miscwarns", $issuer, $msg)){
			case 1:
				$this->tell(TextFormat::RED . "You have been warned for $msg. Please disable them as soon as possible, or you will be banned.");
				break;
			case 2:
				$this->tell(TextFormat::RED . "You have been banned for 14 days for $msg.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 1209600);
				break;
			case 3:
				$this->tell(TextFormat::RED . "This is your third time getting warned for $msg. You have been banned for 45 days.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 3888000);
				break;
			default:
				$this->tell("You have been banned for 75 days for $msg after 3 previous warnings.");
				$this->banPlayer($issuer->getPlayer()->getName(), $msg, 6480000);
				break;
		}
		$this->tell(str_repeat("~", 32));
		$this->tell(str_repeat("!", 32));
	}
	private function warn($column, Session $issuer, $msg){
		$this->tell("$issuer issued you a warning: $msg!");
		$times = ++$this->mysqlSession->data[$column];
		$this->tell("This is your %s time being warned for this!", $ord = $times . MUtils::num_getOrdinal($times));
		$this->mysqlSession->setData();
		$issuer->tell("Warned $this for the $ord time");
		return $times;
	}
	/**
	 * @param $issuer
	 * @param $msg
	 * @param $length
	 */
	public function banPlayer($issuer, $msg, $length){
		$ip = $this->getPlayer()->getAddress();
		$version = $this->getMain()->getDescription()->getVersion();
		$now = time();
		$this->getMain()->getMySQLi()->query("INSERT INTO ipbans(ip,msg,issuer,creation,length,connector)VALUES(%s,%s,%s,from_unixtime($now),$length,'LegionPE_Eta_v$version|MySQLi@pe.legionpvp.eu')", MysqlConnection::RAW, $ip, $msg, $issuer);
		$expiry = (new \DateTime)->setTimestamp(time() + (int) $length);
		$this->getMain()->getServer()->getNameBans()->addBan(strtolower($this->getPlayer()->getName()), $msg, $expiry, "LegionPE Session::banPlayer()");
		$this->kick($msg, 200);
	}
	public function issueWarningEntry($class, $pts, $msg, $issuer){
		$this->issueWarning($w = new Warning($this->getUID(), $class, $pts, $msg, $issuer));
		return $w;
	}
	public function issueWarning(Warning $warning){
		$this->addWarningPoints($warning->pts);
		$warning->insert($this->getMain()->getMySQLi());
		$this->recalcPenalty($warning->issuer, $warning);
	}

	public function onCommand(Command $cmd, array $args){
		switch(strtolower($cmd->getName())){
			case "auth":
				if(!isset($args[1])){
					$this->tell("Usage: /auth <option> <value>");
					$this->tell("| OPTION |    VALUES    |");
					$this->tell("|   ip   | no/hist/last |");
					$this->tell("-------------------------");
					$this->tell("Example: /auth ip no");
				}
				switch($opt = strtolower(array_shift($args))){
					case "ip":
						$values = [self::IPCONFIG_DISABLE => "no", self::IPCONFIG_LASTIP => "yes"];
						$pos = array_search($value = array_shift($args), $values);
						if($pos === false){
							$this->tell("Usage: /auth ip no|yes");
							return;
						}
						$this->mysqlSession->data["ipconfig"] = $pos;
						$this->tell("Your IP config has been set to '%s'.", $values[$pos]);
				}
				return;
			case "chat":
				if(!isset($args[0])){
					$this->tell("Wrong usage. Use '/help chat' for help.");
					return;
				}
				switch($act = strtolower(array_shift($args))){
					case "on":
						if(isset($args[0])){
							if($this->isIgnoringChannel($args[0])){
								$this->tell("You no longer ignore chat messages from #$args[0].");
								$this->unignoreChannel($args[0]);
								return;
							}
							$this->tell("You did not ignore chat messages from #$args[0]!");
							return;
						}
						if($this->isChatOn()){
							$this->tell("Chat is already on!");
							return;
						}
						$this->setChat(true);
						$this->tell("Chat is now turned on.");
						return;
					case "off":
						if(isset($args[0])){
							if(!$this->isIgnoringChannel($args[0])){
								$this->tell("You are now ignoring chat messages from #$args[0].");
								$this->ignoreChannel($args[0]);
								return;
							}
							$this->tell("You are already ignoring chat messages from #$args[0]!");
							return;
						}
						if(!$this->isChatOn()){
							$this->tell("Chat is already off!");
							return;
						}
						$this->setChat(false);
						$this->tell("Chat is now turned off.");
						return;
				}
				$this->tell("Unknown command. Use '/help chat' for help.");
				return;
			case "channel":
				if(!isset($args[0])){
					$this->tell("Wrong usage. Use '/help chan' for help.");
					return;
				}
				switch($subcmd = strtolower(array_shift($args))){
					case "join":
						$this->tell("Sorry, command not implemented.");
						if(true){
							return;
						}
						if(!isset($args[0])){
							$this->tell("Wrong usage. Use '/help chan' for help.");
							return;
						}
						$ch = array_shift($args);
						$chan = $this->getMain()->getChannelManager()->getChannel($ch);
						if(!($chan instanceof Channel)){
							if(!$this->isOper()){
								$this->tell("You must be a chat oper to create new channels!");
								return;
							}
						}
						if(!$chan->canFreeJoin()){
							if(!$this->isOper()){
								$this->tell("You must be a chat oper to join closed channels!");
							}
						}
						$chan = $this->getMain()->getChannelManager()->joinChannel($this, $ch, Channel::CLASS_CUSTOM);
						$this->tell("You joined channel {$chan->getName()}.");
						return;
					case "quit":
						$this->tell("Cannot quit channel: feature not implemented.");
						return;
					case "switch":
						if(!isset($args[0])){
							$this->tell("Wrong usage. Use '/help chan' for help.");
							return;
						}
						$target = array_shift($args);
						$chan = $this->getMain()->getChannelManager()->getChannel($target);
						if(!($chan instanceof Channel)){
							$this->tell("No such channel '$target'.");
							return;
						}
						if(!$chan->isSubscribing($this) and !$this->isOper()){
							$this->tell("You must be a chat oper to talk on non-subscribing channels!");
							return;
						}
						$this->writeToChannel = $chan;
						$this->tell("You are now talking on channel '$chan'.");
						return;
					case "current":
						$this->tell("Current channel: " . $this->writeToChannel->getName());
						return;
					case "list":
						$this->tell("List of channels on the server: ");
						$this->tell(implode(", ", array_keys($this->getMain()->getChannelManager()->listChannels())));
						return;
					case "sub":
					case "subscribing":
						$this->tell("Subscribing channels:");
						$this->tell(implode(", ", array_map(function(Channel $chan){
							return $chan->getName();
						}, array_filter($this->getMain()->getChannelManager()->listChannels(), function(Channel $chan){
							return $chan->isSubscribing($this);
						}))));
						return;
					case "t":
					case "team":
						$team = $this->getTeam();
						if(!($team instanceof Team)){
							$this->tell("You aren't in a team, so you don't have a team channel to join.");
							return;
						}
						$chan = $team->getChannel();
						$this->writeToChannel = $chan;
						$this->tell("You are now talking on $chan.");
						return;
					case "g":
					case "game":
					case "h":
					case "hub":
						$game = $this->getGame();
						if(!($game instanceof Game)){
							$chan = $this->getMain()->getDefaultChannel();
						}
						else{
							$chan = $game->getDefaultChatChannel();
						}
						$this->writeToChannel = $chan;
						$this->tell("You are now talking on $chan.");
						return;
				}
				return;
			case "coins":
				$this->tell("You have %g coins", $this->getCoins());
				return;
			case "disguise":
				if(!isset($args[0])){
					$this->tell("Usage: /dg <new display name>");
					return;
				}
				$name = array_shift($args);
				$this->getPlayer()->setDisplayName($name);
				$this->getPlayer()->setNameTag($name);
				$this->tell("Done. Rejoin to reset.");
				return;
			case "eval":
				$code = implode(" ", $args);
				$this->getMain()->getLogger()->alert($this->getPlayer()->getName() . " is evaluating code:\n$code");
				eval($code);
				return;
			case "grindcoin":
				if(!$this->canActivateGrindCoins($secs)){
					$this->tell("You have to wait for at least %s to activate coins grinding again.", MUtils::time_secsToString($secs));
					return;
				}
				if(!$this->wannaGrind){
					$this->tell("After enabling coins grinding, coins you received will be multiplied by %g times. This doesn't apply to spending coins.", Settings::coinsFactor($this, true));
					$this->tell("It will last for %s each time, and you can't enable it again %s after activation.", MUtils::time_secsToString(Settings::getGrindDuration($this)), MUtils::time_secsToString(Settings::getGrindActivationWaiting($this)));
					$this->tell("Run /grindcoin again to confirm enabling coins grinding.");
					$this->wannaGrind = true;
					return;
				}
				$this->wannaGrind = false;
				$this->mysqlSession->data["lastgrind"] = time();
				$this->tell("Coins grinding has been enabled!");
				return;
			case "ignore":
				if(!isset($args[0])){
					$this->tell("Wrong usage. Use '/help ignore' for help.");
					return;
				}
				$player = $this->sesList->getSession($name = array_shift($args));
				if(!($player instanceof Session)){
					$this->tell("$name isn't online!");
					return;
				}
				if(stripos($this->ignoring, "," . $player->getPlayer()->getName() . ",") !== false){
					$this->tell("You are already ignoring $name!");
					return;
				}
				$this->ignoring .= strtolower($player->getPlayer()->getName()) . ",";
				$this->tell("You are now ignoring chat messages from $name.");
				return;
			case "unignore":
				if(!isset($args[0])){
					$this->tell("Wrong usage. Usage: /unignore <player>");
					return;
				}
//				$session = $this->sesList->getSession($name = array_shift($args));
//				if(!($session instanceof Session)){
//					$this->tell("You can only unignore an online player.");
//					return;
//				}
//				$id = $session->getID() . ",";
				// ^^^^^^^^^^^^^^^^^^^^^^^ these must be used if getID() is changed into something else
				$id = ($name = array_shift($args)) . ",";
				$pos = stripos($this->ignoring, "," . $id);
				if($pos === false){
					$this->tell("You are not ignoring $name!");
					return;
				}
				$new = substr($this->ignoring, 0, $pos) . substr($this->ignoring, $pos + strlen($id)); // delete the comma in front and the name; leave the comma at the back
				$this->ignoring = $new;
				$this->tell("You are no longer ignoring chat messages from $name.");
				return;
			case "info":
				if(!isset($args[0])){
					$args = ["guide"];
				}
				$item = array_shift($args);
				if($item === "uid"){
					$this->tell("Your user ID: %d", $this->uid);
					return;
				}
				if($item === "session"){
					$session = "unknown";
					foreach((new \ReflectionClass($this))->getConstants() as $name => $value){
						if($value === $this->session){
							$session = $name;
							break;
						}
					}
					$this->tell("Current session: " . $session);
					return;
				}
				if($item === "coins"){
					$this->tell("You have %d coins.", $this->getCoins());
					return;
				}
				$res = $this->main->getResource("info/$item.md");
				if(is_resource($res)){
					$text = stream_get_contents($res);
					$this->tell($text);
					$page = isset($args[1]) ? intval($args[1]):1;
					$lines = isset($args[2]) ? $args[2]:5;
					$this->sendPagedText($text, $page, $lines);
				}
				else{
					$this->tell("Info '$item'' not available.");
				}
				return;
			case "invnorm":
				$this->invSession = self::INV_NORMAL_ACCESS;
				$this->tell("Done.");
				return;
			case "mb":
				if(!isset($args[0])){
					$this->tell("Usage: /mb <player> <reason ...>");
					return;
				}
				$player = $this->sesList->getSession($name = array_shift($args));
				if($player instanceof Session){
					$addr = $player->getPlayer()->getAddress();
					$length = 2419200; // 7 days
					if($args[0] === "-t"){
						array_shift($args);
						$length = 60 * 60 * 24 * floatval(array_shift($args));
					}
					$msg = implode(" ", $args);
					$this->getMain()->getMySQLi()->query("INSERT INTO ipbans(ip,msg,issuer,creation,length,connector)VALUES(%s,%s,%s,from_unixtime(%d),%d,%s);", MysqlConnection::RAW, $addr, $msg, $this->getPlayer()->getName(), time(), (int) $length, "LegionPE_Eta|MySQLi@pe.legionpvp.eu");
					$expiryDate = new \DateTime;
					$expiryDate->setTimestamp(time() + (int) $length);
					$this->getMain()->getServer()->getNameBans()->addBan(strtolower($player->getPlayer()->getName()), $msg, $expiryDate, $this->getPlayer()->getName());
					$days = $length / 60 / 60 / 24;
					$staffMsg = "<StaffChan>IP $addr of $player has been banned for $days day(s): $msg.";
					$player->kick("You have been IP-banned for $days day(s): $msg", 200);
					$player->tell("You are going to be kicked in 10 seconds.");
					$this->getMain()->getServer()->broadcast($staffMsg, Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				}
				else{
					$this->tell("Player $name not found!");
				}
				return;
			case "mbr":
				if(!isset($args[0])){
					$this->tell("Usage: /mbr <ip> [-t <days, default 7>] <reason ...>");
					return;
				}
				$ip = array_shift($args);
				$length = 2419200; // 7 days
				if($args[0] === "-t"){
					array_shift($args);
					$length = 60 * 60 * 24 * floatval(array_shift($args));
				}
				$msg = implode(" ", $args);
				$this->getMain()->getMySQLi()->query("INSERT INTO ipbans(ip,msg,issuer,creation,length)VALUES(%s,%s,%s,from_unixtime(%d),%d);", MysqlConnection::RAW, $ip, $msg, $this->getPlayer()->getName(), time(), (int) $length);
				$this->tell("IP $ip has been IP-banned. If there are any players with that address online, kick them yourself.");
				return;
			case "mk":
				if(!isset($args[0])){
					$this->tell("Usage: /mk <player> <reason ...>");
					return;
				}
				if(($kicked = $this->getMain()->getSessions()->getSession($subname = array_shift($args))) instanceof Session){
					$kicked->kick($msg = implode(" ", $args));
					$this->tell("Kicked $kicked: $msg");
					$this->getMain()->getServer()->broadcast("<AdminChan>$kicked has been kicked by $this: $msg", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				}
				else{
					$this->tell("Player $subname cannot be found");
				}
				return;
			case "mute":
				if($this->getRank() & Settings::RANK_PERM_MOD === 0){
					$this->tell("You don't have permission to mute a player. Use /ignore <player> instead.");
					return;
				}
				if(!isset($args[0])){
					$this->tell("Usage: /mute <player> [length in minutes]");
					return;
				}
				$player = $this->sesList->getSession($args[0]);
				if(!($player instanceof Session)){
					$this->tell("Player %s is not online!", $args[0]);
					return;
				}
				$seconds = 60 * 15; // 15 minutes
				if(isset($args[1]) and is_numeric($args[1])){
					$seconds = (int) (60 * floatval($args[1]));
				}
				$player->mute($seconds);
				$msg = sprintf("been muted by %s for %d minutes: %s", $this->getPlayer()->getDisplayName(), $seconds / 60, implode(" ", array_slice($args, 2)));
				$player->tell("You have $msg");
				$this->getMain()->getServer()->broadcast("<StaffChan> $player has $msg", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				return;
			case "unmute":
				if(!isset($args[0])){
					$this->tell("Usage: /unmute <player>");
					return;
				}
				$player = array_shift($args);
				$other = $this->sesList->getSession($player);
				if($other instanceof Session){
					$other->unmute();
					$other->tell("You have been unmuted by $this.");
					$this->main->getServer()->broadcast("<StaffChan> $other has been unmuted by $this.", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				}
				else{
					$this->tell("$player is not online!");
				}
				return;
			case "hub":
			case "quit":
				if(!$this->switchSession(self::SESSION_GAME_HUB)){
					$this->tell("Quitting refused!");
					return;
				}
				$this->getPlayer()->getInventory()->clearAll();
				$this->teleport(Settings::loginSpawn($this->getMain()->getServer()));
				return;
			case "setblock":
				if(!isset($args[3])){
					$this->tell("Wrong usage.");
					return;
				}
				list($x, $y, $z, $block) = $args;
				$block = explode(":", $block, 2);
				if(!isset($block[1])){
					$block[1] = 0;
				}
				list($id, $damage) = $block;
				$v3 = new Vector3((int) $x, (int) $y, (int) $z);
				$this->getPlayer()->getLevel()->setBlock($v3, Block::get((int) $id, (int) $damage));
				$this->tell("Set block %s to %d:%d", "$v3->x,$v3->y,$v3->z", (int) $id, (int) $damage);
				return;
			case "showall":
				$this->tell("This command is no longer necessary as it is being automatically run every 5 seconds.");
				foreach($this->getPlayer()->getLevel()->getPlayers() as $p){
					if($this->getPlayer()->canSee($p)){
						$p->spawnTo($this->getPlayer());
					}
				}
				return;
			case "stats":
				if(($game = $this->getGame()) instanceof Game){
					$this->tell($game->onStats($this, $args));
				}
				else{
					$this->tell("You aren't in a game!");
				}
				return;
			case "tag":
				if(!isset($args[0])){
					$args = ["help"];
				}
				switch(array_shift($args)){
					case "on":
						$this->mysqlSession->data["notag"] = 0;
						$this->tell("Re-enabled text tags");
						return;
					case "off":
						$this->mysqlSession->data["notag"] = 1;
						$this->tell("Disabled text tags");
						return;
					case "check":
						$this->tell("Your tag is " . (($this->mysqlSession->data["notag"] === 0) ? "on":"off"));
						return;
				}
				$this->tell("Usage: /tag on|off|check");
				return;
			case "team":
				if(!isset($args[0])){
					send_help:
					$this->tell("Usage: /team create|join|quit|info|promote|demote|members|open|close|invite [args ...]");
					$this->tell("/team create <name>: create a closed team");
					$this->tell("/team join <name>: join a team");
					$this->tell("/team quit: quit your team");
					$this->tell("/team info [name]: shows info about your team or the specified team");
					$this->tell("/team promote|demote <member>: promote/demote a member");
					$this->tell("/team members: show a list of members and ranks");
					$this->tell("/team open|close: open/close your team to joining without invitations");
					$this->tell("/team (un)invite [player]: send/remove an invitation to an online player to join the team, or view the players invited");
					return;
				}
				switch($sub = array_shift($args)){
					case "create":
						if(($this->getRank() & (Settings::RANK_SECTOR_IMPORTANCE | Settings::RANK_SECTOR_PERMISSION)) < 2){
							$this->tell("You don't have permission to create a team. Donate and upgrade your account to do so!");
							return;
						}
						if(!isset($args[0])){
							$this->tell("Usage: /team create <name>: create an open team");
							return;
						}
						if($this->getTeam() instanceof Team){
							$this->tell("You are already in a team!");
							return;
						}
						$name = array_shift($args);
						if(preg_match('#^[A-Za-z][A-Za-z0-9_\\-]{2,62}$#', $name) === 0){
							$this->tell("A team name must start with an alphabet, must only contain alphabets, numerals, underscore and hyphens, must be at least 3 characters long and must not be longer than 63 characters.");
							return;
						}
						if($this->getMain()->getTeamManager()->getTeamByExactName($name)){
							$this->tell("A team with this name already exists!");
							return;
						}
						$team = new Team($this->getMain(), $this->getMain()->getMySQLi()->nextTID(), $name, Settings::team_maxCapacity($this->getRank()), false, [$this->getUID() => Team::RANK_LEADER]);
						$this->mysqlSession->data["tid"] = $team->tid;
						$this->mysqlSession->data["teamrank"] = Team::RANK_LEADER;
						$this->getMain()->getTeamManager()->addTeam($team);
						$this->tell("New closed team $team created! Team ID: #$team->tid");
						$this->tell("Please rejoin this server to make sure all changes have been updated.");
						return;
					case "join":
						if(!isset($args[0])){
							$this->tell("Usage: /team join <name>: (request to if needed) join a team");
							return;
						}
						if($this->getTeam() instanceof Team){
							$this->tell("You are already in a team!");
							return;
						}
						$team = $this->getMain()->getTeamManager()->getTeamByName($name = array_shift($args));
						if(!($team instanceof Team)){
							$this->tell("Team \"$name\" not found");
							return;
						}
						if(!$team->canJoin($this)){
							$this->tell("You must be invited to enter the team! Or is the team already full?");
							return;
						}
						$team->join($this);
						foreach($this->getMain()->getTeamManager()->getSessionsOfTeam($team) as $ses){
							$ses->tell("$this joined the team!");
						}
						if(isset($team->invited[$this->getUID()])){
							unset($team->invited[$this->getUID()]);
						}
						$this->tell("You have joined team $team. You are going to be kicked in 3 seconds to apply the changes.");
						$this->kick("Joining a team", 60);
						return;
					case "quit":
						$team = $this->getTeam();
						if(!($team instanceof Team)){
							$this->tell("You aren't in a team!");
							return;
						}
						if($this->mysqlSession->data["teamrank"] === Team::RANK_LEADER){
							foreach($this->getMain()->getTeamManager()->getSessionsOfTeam($team) as $s){
								if($s === $this){
									continue;
								}
								$s->tell("Team leader has disbanded the team!");
								$team->quit($s);
							}
							$this->tell("Your team has been disbanded.");
							$team->quit($this);
							$this->getMain()->getTeamManager()->rmTeam($team);
							return;
						}
						$team->quit($this);
						$this->tell("You have successfully quitted your team. You are going to be kicked in 3 seconds to apply the changes.");
						$this->kick("Quitting from a team", 60);
						return;
					case "kick":
						if(!(($team = $this->getTeam()) instanceof Team)){
							$this->tell("You aren't in a team!");
							return;
						}
						if(!isset($args[0])){
							$this->tell("Usage: /team kick <member>");
							return;
						}
						if(!(($session = $this->sesList->getSession($name = array_shift($args))) instanceof Session)){
							$this->tell("Player '$name' is not online!");
							return;
						}
						if($session->getTeam() !== $team){
							$this->tell("$session is not in your team!");
							return;
						}
						$myRank = $this->mysqlSession->data["teamrank"];
						$hisRank = $session->getMysqlSession()->data["teamrank"];
						if($hisRank < $myRank and $myRank >= Team::RANK_CO_LEADER){
							$team->quit($session);
							$session->tell("You have been kicked out of Team $team by $this!");
							$this->tell("You have kicked $session out of your team!");
							$session->tell("You are going to be forcefully kicked in 3 seconds to apply the changes.");
							$session->kick("Kicked from a team", 60);
							$this->tell("The next time $session rejoin, the changes will be fully updated.");
							$session->unsubscribeFromChannel($team->getChannel());
						}
						elseif($hisRank < $myRank){
							$this->tell("You must be at least a Co-Leader to kick members out of your team!");
						}
						else{
							$this->tell("You can only kick members of lower rank than you out of the team!");
						}
						return;
					/** @noinspection PhpMissingBreakStatementInspection */
					case "promote":
						$promote = true;
					case "demote":
						$promote = isset($promote);
						$team = $this->getTeam();
						if(!($team instanceof Team)){
							$this->tell("You aren't in a team!");
							return;
						}
						if(!isset($args[0])){
							$this->tell("Usage: /team promote|demote <member>: promote/demote a member");
							return;
						}
						$other = $this->getMain()->getSessions()->getSession($name = array_shift($args));
						if(!($other instanceof Session)){
							$this->tell("Player $name is not online!");
							return;
						}
						$otherUid = $other->getUID();
						if(!isset($team->members[$otherUid])){
							$this->tell("$other isn't in your team!");
							return;
						}
						$myRank = $team->members[$this->getUID()];
						$hisRank = $team->members[$otherUid];
						if($hisRank < $myRank and $myRank >= Team::RANK_CO_LEADER){
							if($hisRank === Team::RANK_CO_LEADER and $promote){
								$this->tell("There can only be one leader per team!");
								return;
							}
							if($hisRank === Team::RANK_JUNIOR and !$promote){
								$this->tell("You can't demote a junior member!");
							}
							$team->members[$otherUid] = ($promote ? ++$hisRank : --$hisRank);
							$name = Team::$RANK_NAMES[$hisRank];
							$other->getMysqlSession()->data["teamrank"] = $hisRank;
							$other->tell("You have been %s into a $name in your team.");
							$this->tell("You have %s $other into a $name in your team.", $promote ? "promoted":"demoted");
						}
						else{
							$this->tell("Your rank is not high enough to do that!");
						}
						return;
					case "members":
						if(isset($args[0])){
							$name = array_shift($args);
							$team = $this->getMain()->getTeamManager()->getTeamByName($name);
						}
						else{
							$team = $this->getTeam();
						}
						if(!($team instanceof Team)){
							$this->tell("Usage: /team members [team name]");
							return;
						}
						$this->tell("Members in $team->name: (%d / %d)", count($team->members), $team->maxCapacity);
						$members = array_fill_keys(array_keys(Team::$RANK_NAMES), []);
						$this->getMain()->getTeamManager()->saveTeam($team);
						$sess = array_map(function(Session $session){
							return $session->getMysqlSession();
						}, $this->getMain()->getTeamManager()->getSessionsOfTeam($team));
						if(count($sess) > 0){
							MysqlSession::saveData($sess, $this->getMain()->getMySQLi());
						}
						$result = $this->getMain()->getMySQLi()->query("SELECT names,teamrank FROM players WHERE tid=%d", MysqlConnection::ALL, $team->tid);
						foreach($result as $r){
							$members[(int) $r["teamrank"]][] = substr($r["names"], 0, -1);
						}
						foreach($members as $rank => $group){
							$this->tell("%s: %s", Team::$RANK_NAMES[$rank], implode(", ", $group));
						}
						return;
					/** @noinspection PhpMissingBreakStatementInspection */
					case "open":
						$open = true;
					case "close":
						$open = isset($open);
						if(!(($team = $this->getTeam()) instanceof Team)){
							$this->tell("You aren't in a team!");
							return;
						}
						if($this->mysqlSession->data["teamrank"] < Team::RANK_CO_LEADER){
							$this->tell("You don't have permission to modify your team's open/closed status!");
							return;
						}
						if($team->open !== $open){
							$team->open = $open;
							$this->getMain()->getServer()->broadcastMessage("Team $team->name is now a $sub team!");
						}
						else{
							$this->tell("Your team is already $sub!");
						}
						return;
					case "require":
					case "requires":
						if(!(($team = $this->getTeam()) instanceof Team)){
							$this->tell("You aren't in a team!");
							return;
						}
						if(!isset($args[0])){
							$this->tell("Usage: /team require <requirements ...> (separate lines using `|`)");
							return;
						}
						if($this->mysqlSession->data["teamrank"] < Team::RANK_CO_LEADER){
							$this->tell("You must be at least a Co-Leader to change team requirements!");
							return;
						}
						$team->requires = implode(" ", $args);
						$this->tell("Team requirements have been changed to:");
						foreach(array_map("trim", explode("|", $team->requires)) as $require){
							$this->tell($require);
						}
						return;
					case "rule":
					case "rules":
						if(!(($team = $this->getTeam()) instanceof Team)){
							$this->tell("You aren't in a team!");
							return;
						}
						if(!isset($args[0])){
							$this->tell("Usage: /team rule <rules ...> (separate lines using `|`)");
							return;
						}
						if($this->mysqlSession->data["teamrank"] < Team::RANK_CO_LEADER){
							$this->tell("You must be at least a Co-Leader to change team rules!");
							return;
						}
						$team->rules = implode(" ", $args);
						$this->tell("Team rules have been changed to:");
						foreach(array_map("trim", explode("|", $team->rules)) as $rule){
							$this->tell($rule);
						}
						return;
					/** @noinspection PhpMissingBreakStatementInspection */
					case "invite":
						$invite = true;
					case "uninvite":
						$invite = isset($invite);
						if(!(($team = $this->getTeam()) instanceof Team)){
							$this->tell("You are not in a team!");
							return;
						}
						if($this->mysqlSession->data["teamrank"] < Team::RANK_CO_LEADER){
							$this->tell("You don't have permission to add members into your team!");
							return;
						}
						if(!isset($args[0])){
							$names = [];
							foreach($team->invited as $uid => $r){
								$ses = $this->getMain()->getSessions()->getSessionByUID($uid);
								if($ses instanceof Session){
									$names[] = $ses->getPlayer()->getName();
								}
								else{
									$names[] = substr($this->getMain()->getMySQLi()->query("SELECT names FROM players WHERE uid=$uid", MysqlConnection::ASSOC)["names"], 0, -1);
								}
							}
							$this->tell("Members invited: " . implode(", ", $names));
							return;
						}
						$name = array_shift($args);
						$ses = $this->getMain()->getSessions()->getSession($name);
						if(!($ses instanceof Session)){
							$this->tell("Player $name not found!");
							return;
						}
						if($invite and ($ses->getTeam() instanceof Team)){
							$this->tell("$ses is already in a team! Ask him to quit his team in order to invite him.");
						}
						if(isset($team->members[$ses->getUID()])){
							$this->tell("Player is already in team!");
							return;
						}
						if($invite ? !$team->invite($ses) : !$team->uninvite($ses)){
							$this->tell($invite ? "$ses was already invited!" : "$ses wasn't invited!");
							return;
						}
						$this->tell("$ses has been invited.");
						$ses->tell("$this invited you into team $team->name!");
						return;
					case "info":
						if(isset($args[0])){
							$team = $this->getMain()->getTeamManager()->getTeamByName($name = array_shift($args));
							if(!($team instanceof Team)){
								$this->tell("Team $name not found!");
								return;
							}
						}
						else{
							$team = $this->getTeam();
							if(!($team instanceof Team)){
								$this->tell("Usage: /team info [name]");
								return;
							}
						}
						$this->getMain()->updateGameSessionData();
						$info = $team->getStats();
						$this->tell("%s team $team ($info->totalMembers/$team->maxCapacity members, %d are new)", $team->open ? "Opened":"Invite-only", $info->totalMembers - $info->oldMembers);
						$this->tell("Requirements to join the team: ");
						foreach(array_map("trim", explode("|", $team->requires)) as $line){
							$this->tell($line);
						}
						$this->tell("Team rules:");
						foreach(array_map("trim", explode("|", $team->rules)) as $line){
							$this->tell($line);
						}
						$kd = ($info->pvpDeaths > 0) ? ((string) round($info->pvpKills / $info->pvpDeaths, 3)) : "N/A";
						$this->tell("KitPvP: $info->pvpKills kills, $info->pvpDeaths deaths, max killstreak $info->pvpMaxStreak, Overall K/D $kd");
						$this->tell("Parkour: $info->parkourWins completions, average {$info->parkourAvgFalls()} falls per completion");
						$this->tell("Spleef: $info->spleefWins wins, $info->spleefLosses losses, $info->spleefDraws draws");
						$this->tell("Overall team points: " . round($info->totalPoints() / $info->oldMembers, 3));
						return;
					case "list":
						$this->tell("Teams on this server:");
						$this->tell(implode(", ", array_map(function(Team $team){
							return TextFormat::RED . $team->name . TextFormat::WHITE . " (" . TextFormat::GOLD . count($team->members) . TextFormat::WHITE . "/" . TextFormat::YELLOW . $team->maxCapacity . TextFormat::WHITE . ", " . ($team->open ? (TextFormat::DARK_GREEN . "open") : "") . TextFormat::WHITE . ")";
						}, $this->getMain()->getTeamManager()->getTeams())));
						return;
				}
				goto send_help;
				/** @noinspection PhpUnreachableStatementInspection */
				return;
			case "tpw":
				if(!isset($args[0])){
					$this->tell("Usage: /tpw <world name>");
					return;
				}
				$world = array_shift($args);
				$level = $this->getMain()->getServer()->getLevelByName($world);
				if(!($level instanceof Level)){
					return;
				}
				$this->getPlayer()->teleport($level->getSpawnLocation());
				return;
			case "wads":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnAds($this, implode(" ", $args));
				}
				else{
					$warned->warnAds($this);
				}
				return;
			case "wcap":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnCaps($this, implode(" ", $args));
				}
				else{
					$warned->warnCaps($this);
				}
				return;
			case "wswear":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnSwearing($this, implode(" ", $args));
				}
				else{
					$warned->warnSwearing($this);
				}
				return;
			case "wspam":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnSpamming($this, implode(" ", $args));
				}
				else{
					$warned->warnSpamming($this);
				}
				return;
			case "wimpose":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnImpose($this, implode(" ", $args));
				}
				else{
					$warned->warnImpose($this);
				}
				return;
			case "wchat":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnChat($this, implode(" ", $args));
				}
				else{
					$warned->warnChat($this);
				}
				return;
			case "wfly":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnFlying($this, implode(" ", $args));
				}
				else{
					$warned->warnFlying($this);
				}
				return;
			case "wsprint":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnSprinting($this, implode(" ", $args));
				}
				else{
					$warned->warnSprinting($this);
				}
				return;
			case "wgh":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnGhostHack($this, implode(" ", $args));
				}
				else{
					$warned->warnGhostHack($this);
				}
				return;
			case "wmod":
				if(!isset($args[0])){
					$this->tell("Usage: " . $cmd->getUsage());
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnMods($this, implode(" ", $args));
				}
				else{
					$warned->warnMods($this);
				}
				return;
			case "wmisc":
				if(!isset($args[1])){
					$this->tell("Usage: /wmisc <player> <reason ...>");
					return;
				}
				$target = array_shift($args);
				if(!(($warned = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell("Player $target not found");
					return;
				}
				if(isset($args[0])){
					$warned->warnMisc($this, implode(" ", $args));
				}
				else{
					$warned->warnMisc($this);
				}
				return;
			default:
				$game = $this->getGame();
				if($game instanceof Game){
					$game->onCommand($cmd, $args, $this);
				}
				else{
					$this->getMain()->getLogger()->warning("Unexpected command /{$cmd->getName()} not handled!");
				}
				return;
		}
	}
	public function testCommandPermission(Command $cmd, &$msg = ""){
		$msg = "You don't have permission to use this command.";
		switch(strtolower($cmd->getName())){
			case "auth":
				return true;
			case "chat":
				return true;
			case "channel":
				return true;
			case "coins":
				return true;
			case "disguise":
				$prec = $this->getRank() & Settings::RANK_SECTOR_PRECISION;
				$perm = $this->getRank() & Settings::RANK_SECTOR_PERMISSION;
				if(($perm & Settings::RANK_PERM_MOD) === Settings::RANK_PERM_MOD){
					if($perm === Settings::RANK_PERM_MOD and $prec === Settings::RANK_PREC_TRIAL){
						$msg = "This command is not available for trial moderators.";
						return false;
					}
					return true;
				}
				$msg = "This command is only available for staff members.";
				return false;
			case "eval":
				if(($this->getRank() & Settings::RANK_PERM_DEV) === Settings::RANK_PERM_DEV){
					return true;
				}
				$msg = "This command is only available for staff members with the PERM_DEV permission.";
				return false;
			case "grindcoin":
				if($this->getRank() & Settings::RANK_IMPORTANCE_DONATOR){
					return true;
				}
				$msg = "Upgrade your account to donator or above to use this command.";
				return false;
			case "ignore":
				return true;
			case "unignore":
				return true;
			case "info":
				return true;
			case "invnorm":
				return true;
			case "mb":
				if(($this->getRank() & Settings::RANK_PERM_MOD) === Settings::RANK_PERM_MOD){
					return true;
				}
				$msg = "This command is only available for staff members.";
				return false;
			case "mbr":
				if(($this->getRank() & Settings::RANK_PERM_MOD) === Settings::RANK_PERM_MOD){
					return true;
				}
				$msg = "This command is only available for staff members.";
				return false;
			case "mk":
				if(($this->getRank() & Settings::RANK_PERM_MOD) === Settings::RANK_PERM_MOD){
					return true;
				}
				$msg = "This command is only available for staff members.";
				return false;
			case "mute":
				if($this->getRank() & Settings::RANK_PERM_MOD){
					return true;
				}
				$msg = "This command is only available for staff members.";
				return false;
			case "unmute":
				if($this->getRank() & Settings::RANK_PERM_MOD){
					return true;
				}
				$msg = "This command is only available for staff members.";
				return false;
			case "hub":
			case "quit":
				if($this->session & self::SES_STATE_GAME){
					return true;
				}
				$msg = "This command is only available for players who are in games.";
				return false;
			case "setblock":
				if(($this->getRank() & Settings::RANK_PERM_WORLD_EDIT) === Settings::RANK_PERM_WORLD_EDIT){
					return true;
				}
				$msg = "This command is only available for staff members with PERM_WORLD_EDIT permission.";
				return false;
			case "showall":
				return true;
			case "stats":
				if($this->session & self::SES_STATE_GAME){
					return true;
				}
				$msg = "This command is only available for players who are in games.";
				return false;
			case "tag":
				if($this->getRank()){
					return true;
				}
				$msg = "You don't have a tag to hide.";
				return false;
			case "team":
				return true;
			case "tpw":
				if($this->getRank() & Settings::RANK_PERM_MOD){
					return true;
				}
				$msg = "This command is only for staff members.";
				return false;
			case "wads":
			case "wcap":
			case "wswear":
			case "wspam":
			case "wimpose":
			case "wchat":
			case "wfly":
			case "wsprint":
			case "wgh":
			case "wmod":
			case "wmisc":
				if($this->getRank() & Settings::RANK_PERM_MOD){
					return true;
				}
				$msg = "This command is only available for staff members.";
				return false;
			default:
				$game = $this->getGame();
				if($game instanceof Game){
					return $game->testCommandPermission($cmd, $this);
				}
				$msg = "INTERNAL SERVER ERROR: Command permission not handled!";
				return false;
		}
	}

	public function __construct(SessionInterface $auth, PlayerLoginEvent $login){
		$this->player = $login->getPlayer();
		parent::__construct($auth->getMain());
		$this->spawningPosition = $login->getPlayer()->getPosition();
		$this->sesList = $auth;
		$this->main = $auth->getMain();
		$this->mysqlSession = new MysqlSession($this);
		$this->spamDetector = new SpamDetector($this);
		$this->perm = $this->getPlayer()->addAttachment($this->main);
		$rank = $this->getRank();
		if(($rank & Settings::RANK_SECTOR_IMPORTANCE) === Settings::RANK_IMPORTANCE_TESTER){
			$prefix = "6Tester";
		}
		if(($rank & Settings::RANK_SECTOR_IMPORTANCE) === Settings::RANK_IMPORTANCE_DONATOR){
			$prefix = "6Donator";
		}
		if(($rank & Settings::RANK_SECTOR_IMPORTANCE) === Settings::RANK_IMPORTANCE_DONATOR_PLUS){
			$prefix = "6Donator+";
		}
		if(($rank & Settings::RANK_SECTOR_IMPORTANCE) === Settings::RANK_IMPORTANCE_VIP){
			$prefix = "6VIP";
		}
		if(($rank & Settings::RANK_SECTOR_IMPORTANCE) === Settings::RANK_IMPORTANCE_VIP_PLUS){
			$prefix = "6VIP+";
		}
		if(($rank & Settings::RANK_PERM_MOD) === Settings::RANK_PERM_MOD){
			$prefix = "bMod";
		}
		if(($rank & Settings::RANK_PERM_ADMIN) === Settings::RANK_PERM_ADMIN){
			$prefix = "bAdmin";
		}
		if(($rank & Settings::RANK_PERM_DEV) === Settings::RANK_PERM_DEV){
			$prefix = "bDev";
		}
		if(($rank & Settings::RANK_PERM_OWNER) === Settings::RANK_PERM_OWNER){
			$prefix = "bOwner";
		}
		if(($rank & Settings::RANK_PERM_STAFF) === Settings::RANK_PERM_STAFF){
			$prefix = "bStaff";
		}
		if(($rank & Settings::RANK_PREC_TRIAL) and isset($prefix)){
			$prefix = substr($prefix, 0, 1) . "Trial " . substr($prefix, 1);
		}
		if(($rank & Settings::RANK_PREC_HEAD) and isset($prefix)){
			$prefix = substr($prefix, 0, 1) . "Head " . substr($prefix, 1);
		}
		if(($rank & Settings::RANK_PREC_HEAD) and ($rank & Settings::RANK_PERM_STAFF) === Settings::RANK_PERM_STAFF){
			$prefix = "bHeadOfStaff";
		}
		switch($rank & Settings::RANK_SECTOR_DECOR){
			case Settings::RANK_DECOR_YOUTUBER:
				$decoration = "6YT";
				break;
		}
		if(isset($prefix)){
			$prefix = "[§" . (isset($decoration) ? "$decoration|§":"") . $prefix . "§7]";
		}
		else{
			$prefix = isset($decoration) ? "[§$decoration" . "§7]":"";
		}
		$name = "$prefix{$this->getPlayer()->getName()}";
		$this->getPlayer()->setDisplayName(self::UNAUTHENTICATED_TAG . $name);
		$this->getPlayer()->setNameTag($name);
		$this->writeToChannel = $this->personalChannel = $this->main->getChannelManager()->joinChannel($this, "Player_" . $this->getPlayer()->getName(), Channel::CLASS_PERSONAL);
	}
	public function join(PlayerJoinEvent $event){
		$this->joinTime = time();
		$this->spawningPosition = $event->getPlayer()->getPosition();
		$this->setInvisible(self::INVISIBLE_UNAUTH);
		$event->setJoinMessage("");
		$result = $this->mysqlSession->getData();
		if(is_array($result)){
			$this->uid = $result["uid"];
			if($result["hash"] === ZERO_HASH){
				$this->auth(null);
			}
			$ipconfig = $result["ipconfig"];
//			if((int) $ipconfig === self::IPCONFIG_ANYIP){
//				foreach(explode(",", $result["histip"]) as $ip){
//					if($this->player->getAddress() === $ip){
//						$this->auth("matching an IP once authenticated with");
//						return;
//					}
//				}
//			}
			if((int) $ipconfig === self::IPCONFIG_LASTIP and $result["lastip"] === $this->getPlayer()->getAddress()){
				$this->auth("matching the last IP authenticated with");
				return;
			}
			$this->session = self::SESSION_LOGIN;
			$this->tell("This account has been registered. Please login by typing the password directly into chat.");
			$this->ignoring = $result["ignoring"];
			return;
		}
		else{
			$this->session = self::SESSION_REG_INTRO;
			$this->tell(str_repeat("~", 34));
			$this->tell("Welcome to LegionPE!");
			$this->tell("To protect your account, please register your username (%s) by typing a password directly into chat and send it.", $this->getPlayer()->getName());
			$this->tell("Don't worry, nobody else will see that.");
			$this->tell("And remember, don't forget your password!");
			return;
		}
	}
	public function onRun($currentTick){
		if($this->session !== self::SESSION_INIT and !$this->authenticated and (time() - $this->joinTime > 120)){
			$this->getPlayer()->kick(TextFormat::YELLOW . "Failed to authenticate in 120 seconds", false);
		}
		elseif($this->session !== self::SESSION_INIT and
			($this->getRank() & Settings::RANK_PERM_AFK) !== Settings::RANK_PERM_AFK and
			abs(time() - $this->lastActivity) > 90){
			if(\IS_TEST and stripos(get_class($this->getPlayer()), "specter") !== false){
				return;
			}
			$this->getPlayer()->kick(TextFormat::YELLOW . "AFK for more than 90 seconds", false);
		}
		else{
			if(($currentTick % (20 * 60 * 2)) < 20){
				$this->sendRandomBroadcast();
			}
			if(($currentTick % (20)) < 20){
				if($this->getPlayer()->fireTicks > 0){
					$this->getPlayer()->setHealth($this->getPlayer()->getHealth() - 1);
				}
			}
		}
	}
	public function finalize(PlayerQuitEvent $event){
		$event->setQuitMessage("");
		if(($game = $this->getGame()) instanceof Game){
			$game->onQuit($this, true);
		}
		$this->main->getChannelManager()->quit($this);
		$this->getPlayer()->removeAttachment($this->perm);
		$this->mysqlSession->data["lastonline"] = time();
		if($this->authenticated){
			$this->mysqlSession->data["ignoring"] = $this->ignoring;
			$this->mysqlSession->setData($this->mysqlSession->data);
		}
	}

	public function h_onPreCmd(PlayerCommandPreprocessEvent $event){
		$this->activity();
		$category = $this->session & 0b11110000;
		if($category === self::SES_STATE_REG){
			$this->handleRegisterMessage($event);
		}
		elseif($category === self::SES_STATE_LOGIN){
			$this->handleLoginMessage($event);
		}
		else{
			$this->prehandleGameChat($event);
		}
	}
	public function h_onDamage(EntityDamageEvent $event){
		if($event->getCause() === EntityDamageEvent::CAUSE_VOID){
			$event->setCancelled();
			$this->player->teleport($this->player->getLevel()->getSpawnLocation());
		}
		if(!$this->isLoggedIn()){
			$event->setCancelled();
			$this->disableTpListener = true;
			$this->teleport($this->spawningPosition);
			$this->disableTpListener = false;
		}
		/*if($event instanceof EntityDamageByEntityEvent){
			$target = $this->getPlayer();
			$hitter = $event->getDamager();
			if(!($hitter instanceof Player)){
				return;
			}
			if((pow($hitter->x - $target->x, 2) + pow($hitter->z - $target->z, 2)) <= 2.25){
				return;
			}
			$dir = $hitter
//				->add(0, $hitter->getEyeHeight(), 0)
				->subtract(
					$target
//						->add(0, $target->height / 2, 0)
				);
			$yaw = rad2deg(atan2($dir->z, $dir->x)) + 90;
//			if(($len = $dir->length()) === 0){
//				$len = 1;
//			}
//			$pitch = rad2deg(asin($dir->y / $len));
			if((180 - abs(abs($hitter->yaw - $yaw) - 180)) > 90
//				or (180 - abs(abs($hitter->pitch - $pitch) - 180)) > 60
			){
				$event->setCancelled();
				$hitter->sendMessage("You can't attack players behind you!");
			}
		}*/
	}
	public function h_onItemConsume(PlayerItemConsumeEvent $event){
		if(!$this->isLoggedIn()){
			$event->setCancelled();
		}
	}
	public function h_onDropItem(PlayerDropItemEvent $event){
		if(!$this->isLoggedIn()){
			$event->setCancelled();
			$this->sendAuthMessage();
		}
	}
	public function h_onInteract(PlayerInteractEvent $event){
		if(!$this->isLoggedIn()){
			$event->setCancelled();
			$this->sendAuthMessage();
		}
		elseif($this->compassOn and $event->getItem()->getId() === Item::COMPASS){
			$block = $event->getBlock();
			$event->getPlayer()->sendMessage("Block is $block at $block->x,$block->y,$block->z");
			$event->setCancelled();
		}
		elseif($this->getPlayer()->getLevel()->getName() === "world"){
			Settings::portalBoost($this->getPlayer(), $event->getBlock());
		}
	}
	public function h_onRespawn(PlayerRespawnEvent $event){
		if(!$this->isLoggedIn()){
			$this->sendAuthMessage();
		}
		else{
			if(($game = $this->getGame()) instanceof Game){
				$game->onRespawn($event, $this);
			}
		}
	}
	public function h_onMove(PlayerMoveEvent $event){
		if($this->getPlayer()->y < 5 and $this->getPlayer()->getLevel()->getName() === "world"){
			$this->teleport($this->getPlayer()->getLevel()->getSafeSpawn());
		}
		if(!$this->isLoggedIn()){
			$event->setCancelled();
			$this->disableTpListener = true;
			$this->teleport($this->spawningPosition);
			$this->disableTpListener = false;
		}
		elseif(($game = $this->getGame()) instanceof Game){
			$game->onMove($this, $event);
		}
	}
	public function h_onBreak(BlockBreakEvent $event){
		if(!$this->isLoggedIn()){
			$event->setCancelled();
		}
	}
	public function h_onPlace(BlockPlaceEvent $event){
		if(!$this->isLoggedIn()){
			$event->setCancelled();
		}
	}
	public function h_onOpenInv(InventoryOpenEvent $event){
		if(!$this->isLoggedIn()){
			$event->setCancelled();
		}
	}
	public function h_onPickup(InventoryPickupItemEvent $event){
		if(!$this->isLoggedIn()){
			$event->setCancelled();
		}
	}
	public function h_onChat(PlayerChatEvent $event){
		$event->setRecipients([]);
		if($this->spamDetector->onMessage($event->getMessage())){
			if($this->isChatOn() and !$this->isIgnoringChannel($this->writeToChannel->getLowName())){
				$this->writeToChannel->send($this, $event->getMessage());
			}
			else{
				$this->tell("You cannot send chat to a channel you are ignoring or with chat turned off!");
			}
		}
	}
	public function h_onItemHeld(PlayerItemHeldEvent $event){
		switch($this->getInventorySession()){
			case self::INV_CHOOSE_GAME:
				$event->setCancelled();
				$slot = $event->getInventorySlot();
				$gameId = $slot | self::SES_STATE_GAME;
				if($gameId === $this->session){
					$this->tell("You are already here!");
					return;
				}
				$this->setInventorySession(self::INV_NORMAL_ACCESS);
				$game = $this->getGame();
				if($game instanceof Game){
					$game->onQuit($this, false);
					$this->unsubscribeFromChannel($game->getDefaultChatChannel());
				}
				$this->getPlayer()->getInventory()->clearAll();
				if($gameId === self::SESSION_GAME_HUB){
					$this->switchSession(self::SESSION_GAME_HUB);
				}
				else{
					$game = $this->main->getGame($gameId);
					if(!($game instanceof Game)){
						return;
					}
					if(!$this->joinGame($game)){
						$this->tell("This game is not available!");
						$this->setInventorySession(self::INV_CHOOSE_GAME);
					}
				}
				break;
		}
	}
	public function l_onBlockPlace(BlockPlaceEvent $event){
		if(!$this->authenticated or ($this->getRank() & Settings::RANK_PERM_WORLD_EDIT) !== Settings::RANK_PERM_WORLD_EDIT){
			$event->setCancelled();
		}
	}
	public function l_onBlockBreak(BlockBreakEvent $event){
		if(!$this->authenticated or ($this->getRank() & Settings::RANK_PERM_WORLD_EDIT) !== Settings::RANK_PERM_WORLD_EDIT){
			$event->setCancelled();
		}
	}
	public function l_onDamage(EntityDamageEvent $event){
		$event->setCancelled();
	}
	public function mon_onMove(PlayerMoveEvent $event){
		$game = Settings::portal($this->getPlayer(), $this->main);
		if($game instanceof Game) {
			$this->getPlayer()->getInventory()->clearAll();
			if($game->onJoin($this)) {
				$this->tell("You have entered the game: %s", $game->getName());
				$this->switchSession($game->getSessionId());
			}
			else{
				$this->tell("You are refused to enter this game!");
			}
		}else{
			Settings::portalBoost2($this->getPlayer());
			$from = Settings::checkInvisibility($event->getFrom());
			$to = Settings::checkInvisibility($event->getTo());
			if($from and !$to){
				$this->setVisible(self::INVISIBLE_SPAWN);
			}
			elseif(!$from and $to){
				$this->setInvisible(self::INVISIBLE_SPAWN);
			}
		}
	}
	public function mon_onTeleport(EntityTeleportEvent $event){
		if($this->disableTpListener or !$this->authenticated){
			return;
		}
		if($event->getFrom()->getLevel()->getName() !== $event->getTo()->getLevel()->getName()){
			$this->tell("After finishing your current job, please go back to hub. You have quitted all games due to world teleportation.");
			$game = $this->main->getGame($this->session);
			if($game instanceof Game){
				$game->onQuit($this, true);
				$this->unsubscribeFromChannel($game->getDefaultChatChannel());
			}
			$this->session = self::SESSION_GAME_HUB;
		}
		else{

			$from = Settings::checkInvisibility($event->getFrom());
			$to = Settings::checkInvisibility($event->getTo());
			if($from and !$to){
				$this->setVisible(self::INVISIBLE_SPAWN);
			}
			elseif(!$from and $to){
				$this->setInvisible(self::INVISIBLE_SPAWN);
			}
		}
	}
	public function hst_onInteract(PlayerInteractEvent $event){
		if($this->getInventorySession() !== self::INV_NORMAL_ACCESS){
			$this->tell(TextFormat::YELLOW . "Do \"/inv norm\" to change back to normal inventory access so that you can touch blocks.");
			$event->setCancelled();
		}
	}

	public function activity(){
		$this->lastActivity = time();
	}
	private function sendAuthMessage(){
		if(($this->session & 0b11110000) === self::SES_STATE_LOGIN){
			$this->tell("Please login by typing your password directly into chat!");
		}
		if($this->session === self::SESSION_REG_INTRO){
			$this->tell("Please register first by typing your password directly into chat.");
		}
		if($this->session === self::SESSION_REG_REP){
			$this->tell("Please complete the registration first!");
			$this->tell("Please type your password into chat again to confirm.");
		}
		if($this->session === self::SESSION_REG_IP){
			$this->tell("Wait! You haven't finished your registration yet!");
			$this->tellIPAuthSettings();
		}
	}
	private function handleRegisterMessage(PlayerCommandPreprocessEvent $event){
		$event->setCancelled();
		$msg = $event->getMessage();
		$this->tell(str_repeat("~", 34));
		if($this->session === self::SESSION_REG_INTRO){
			if(substr($msg, 0, 1) === "/"){
				$this->tell("I don't think a password starting with a slash is a good idea...");
				$this->tell("Register by typing your password directly into chat!");
				return;
			}
			elseif(strlen($msg) < 4){
				$this->tell("Seriously? Don't you think it is too short to be guessed? Make at least 4 characters.");
				$this->tell("Try again.");
				return;
			}
			$this->tmpPswd = self::hash($msg);
			$this->tell("Thank you. Now, please type in your password again to confirm you didn't enter the wrong thing.");
			$this->session = self::SESSION_REG_REP;
		}
		elseif($this->session === self::SESSION_REG_REP){
			if(self::hash($msg) === $this->tmpPswd){
				$this->tell("The password matched.");
				$this->tellIPAuthSettings();
				$this->session = self::SESSION_REG_IP;
			}
			else{
				$this->tell("The password doesn't match! Let's start all over again...");
				$this->session = self::SESSION_REG_INTRO;
				$this->tell("To protect your account, please register your username (%s) by typing a password directly into chat and send it.", $this->getPlayer()->getName());
				$this->tell("Don't worry, nobody else will see that.");
				$this->tell("And remember, don't forget your password!");
			}
		}
		elseif($this->session === self::SESSION_REG_IP){
			switch(strtolower($msg)){
				case "no":
					$this->tell("OK, thanks. You can always change this option via /auth.");
					$ipconfig = self::IPCONFIG_DISABLE;
					break;
				case "yes":
					$this->tell("Sure! You can always change this option via /auth.");
					$ipconfig = self::IPCONFIG_LASTIP;
					break;
				case "all":
					$this->tell("Sorry, historical IP is now disabled.");
					$this->tell("Maybe I should repeat?");
					$this->tellIPAuthSettings();
					return;
//					$this->tell("OK. You can always change this option via /auth.");
//					$ipconfig = self::IPCONFIG_ANYIP;
//					break;
				default:
					$this->tell("Sorry... I don't understand what the word '$msg' means.");
					$this->tell("Maybe I should repeat?");
					$this->tellIPAuthSettings();
					return;
			}
			$this->uid = $this->main->getMySQLi()->nextUID();
			$data = [
				"uid" => $this->getUID(),
				"names" => $this->getPlayer()->getName() . "|",
				"hash" => $this->tmpPswd,
				"coins" => 100,
				"lastonline" => time(),
				"registry" => time(),
				"rank" => 0,
				"lastip" => $this->getPlayer()->getAddress(),
				"histip" => $this->getPlayer()->getAddress(),
				"ipconfig" => $ipconfig,
				"notag" => 0,
				"lastgrind" => 0,
				"tid" => -1,
				"teamrank" => 0,
				"teamjointime" => 0,
				"ignoring" => ",",
				"primaryname" => strtolower($this->getPlayer()->getName()),
				"ads" => 0,
				"caps" => 0,
				"swears" => 0,
				"spams" => 0,
				"imposes" => 0,
				"chatwarns" => 0,
				"flies" => 0,
				"sprints" => 0,
				"ghwarns" => 0,
				"modswarns" => 0,
				"miscwarns" => 0,
				"ontime" => 0,
				"warnpts" => 0

			];
			$this->mysqlSession->setData($data);
			$this->auth("registration");
		}
	}
	private function handleLoginMessage(PlayerCommandPreprocessEvent $event){
		$event->setCancelled();
		$msg = $event->getMessage();
		if(self::checkHash($msg, self::SALT, $this->mysqlSession->getData()["hash"])){
			$this->auth("password");
		}
		else{
			$this->tell("Password is incorrect!");
			$this->tell("Please try again. Remember you must type it directly into chat.");
			$this->session++;
			$diff = self::SESSION_LOGIN_MAX - $this->session;
			if($diff <= 0){
				$this->getPlayer()->kick(TextFormat::YELLOW . "Failed to authenticate within 5 attempts", false);
			}
			else{
				$this->tell("You have $diff more chance(s) before getting kicked.");
			}
		}
	}
	private function prehandleGameChat(PlayerCommandPreprocessEvent $event){
		if(self::checkHash($event->getMessage(), self::SALT, $this->mysqlSession->getData()["hash"])){
			$event->setCancelled();
			$this->tell(TextFormat::RED . "Never tell other players your password!");
		}
		elseif(stripos($msg = $event->getMessage(), "/me ") === 0){
			$msg = substr($msg, 4);
			if($this->spamDetector->onMessage($msg)){
				if($this->isChatOn() and !$this->isIgnoringChannel($this->writeToChannel->getLowName())){
					$this->writeToChannel->send($this, $msg, true);
				}
				else{
					$this->tell("You cannot send chat to a channel you are ignoring or with chat turned off!");
				}
			}
			$event->setCancelled();
		}
		elseif(stripos($msg, "/say ") === 0){
			$msg = substr($msg, 5);
			$event->setCancelled();
			if($this->getRank() & Settings::RANK_PERM_MOD){
				$this->getMain()->getMandatoryChannel()->send($this->getMain(), $msg);
			}
			else{
				$this->tell("You don't have permission to run this command.");
			}
		}
		/*elseif(stripos($msg, "/tell ") === 0 or stripos($msg, "/msg ") === 0 or stripos($msg, "/w ") === 0){
			if(stripos($msg, "/w ") !== 0){
				$this->tell(TextFormat::YELLOW . "Reminder: You can use alias /w instead of " . strstr($msg, " ", true) . " for private messages.");
				$event->setCancelled();
				$args = explode(" ", $msg);
				$target = array_shift($args);
				if(!(($ses = $this->sesList->getSession($target)) instanceof Session)){
					$this->tell(TextFormat::RED . "Player \"$target\" doesn't exist.");
					return;
				}
				$ses->tell("[" . $this->getRealName() . "->me]" . ($m = implode(" ", $args)));
				$this->tell("[me->" . $ses->getRealName() . "]" . $m);
				if(!$this->queryReminded){
					$this->tell(TextFormat::AQUA . "Reminder: You can use \"/q <player>\" to make all your chat messages sent to <player> only, and use /bc to return to normal chat.");
					$this->queryReminded = true;
				}
			}
		}
		elseif(stripos($msg, "/query ") === 0 or stripos($msg, "/q ") === 0){
			$event->setCancelled();
			$this->queryReminded = true;
			$arg = substr(strstr($msg, " "), 1);
			$this->getMain()->getServer()->dispatchCommand($this->getPlayer(), "/bc /w $arg %s");
		}*/
		elseif(stripos($msg, "/version ") === 0 or stripos($msg, "/plugins ") === 0){
			$event->setCancelled();
			$this->tell(TextFormat::GOLD . "This instance of LegionPE is running " . $this->getMain()->getDescription()->getFullName());
		}
//		$this->getMain()->getLogger()->debug("Session.php prehandled chat from " . $event->getPlayer()->getDisplayName());
	}
	private function tellIPAuthSettings(){
		$this->tell(str_repeat("=", 40));
		$this->tell("Do you want to be automatically authenticated by your IP?");
		$this->tell(str_repeat("=", 40));
		$this->tell("If no, type 'no'.");
		$this->tell("If you want to be authenticated when you login with your " .
			"last authenticated IP, type 'yes'.");
//		$this->tell("If you want to be authenticated when you login with any " .
//			"of the IPs you have authenticated with, type 'all'.");
		$this->tell(str_repeat("=", 40));
	}
	public function switchSession($id){
		if($this->getInventorySession() !== self::INV_NORMAL_ACCESS){
			$this->setInventorySession(self::INV_NORMAL_ACCESS);
		}
		if($this->session === $id){
			return false;
		}
		$game = $this->main->getGame($this->session);
		if($game instanceof Game){
			if(!$game->onQuit($this, false)){
				return false;
			}
			$chan = $game->getDefaultChatChannel();
			$this->unsubscribeFromChannel($chan);
		}
		if($this->writeToChannel->getClass() === Channel::CLASS_MODULAR){
			$this->writeToChannel->unsubscribe($this);
		}
		$this->session = $id;
		$game = $this->main->getGame($id);
		if($game instanceof Game){
			$this->writeToChannel = $this->main->getChannelManager()->joinChannel($this, $game->getDefaultChatChannel(), Channel::CLASS_MODULAR);
		}
		else{
			$this->writeToChannel = $this->main->getDefaultChannel();
			$this->writeToChannel->subscribe($this);
			$this->onHub();
		}
		return true;
	}
	private function onHub(){
		$inv = $this->getPlayer()->getInventory();
		$inv->clearAll();
		$items = [(self::SESSION_GAME_HUB & ~self::SES_STATE_GAME) => new ItemBlock(new Trapdoor)];
		foreach($this->getMain()->getGames($this) as $game){
			$items[$game->getSessionId() & (~self::SES_STATE_GAME)] = $game->getSelectionItem();
		}
		$this->getMain()->getServer()->getScheduler()->scheduleDelayedTask(new CallbackPluginTask($this->getMain(), function() use($items, $inv){
			$inv->setHeldItemSlot(0);
			$this->setInventorySession(self::INV_CHOOSE_GAME);
			foreach($items as $sid => $item){
				$inv->setItem($sid, $item);
			}
			$inv->sendContents($this->getPlayer());
		}), 2);
		$this->tell("Walk into a portal or open your inventory and choose an item to join a game.");
		$this->writeToChannel = $this->main->getDefaultChannel()->subscribe($this);
	}
	public function openChooseGameInv(){
		if($this->getInventorySession() === self::INV_CHOOSE_GAME){
			$this->tell(TextFormat::RED . "You are already choosing a game.");
			return false;
		}
		if(!$this->setInventorySession(self::INV_CHOOSE_GAME)){
			return false;
		}
		$items = [(self::SESSION_GAME_HUB & ~self::SES_STATE_GAME) => new ItemBlock(new Trapdoor)];
		foreach($this->getMain()->getGames($this) as $game){
			$items[$game->getSessionId() & (~self::SES_STATE_GAME)] = $game->getSelectionItem();
		}
		$this->getPlayer()->getInventory()->clearAll();
		$this->getPlayer()->getInventory()->setHeldItemSlot(0);
		foreach($items as $slot => $item){
			$this->getPlayer()->getInventory()->setItem($slot, $item);
		}
		$this->getPlayer()->getInventory()->sendContents($this->getPlayer());
		$this->tell("Sending...");
		return true;
	}
	/**
	 * @param string $issuer
	 * @param Warning|null $lastWarning
	 */
	public function recalcPenalty($issuer, $lastWarning = null){
		$pts = $this->getWarningPoints();
		switch(true){
			case $pts >= 100:
				$ban = 6480000;
				break;
			case $pts >= 80:
				$ban = 5184000;
				break;
			case $pts >= 60:
				$ban = 3888000;
				break;
			case $pts >= 50:
				$ban = 2592000;
				break;
			case $pts >= 40:
				$ban = 1209600;
				break;
			case $pts >= 30:
				$ban = 604800;
				break;
			case $pts >= 28:
				$mute = 7200;
				break;
			case $pts >= 24:
				$mute = 3600;
				break;
			case $pts >= 21:
				$mute = 2700;
				break;
			case $pts >= 18:
				$mute = 1800;
				break;
			case $pts >= 15:
				$mute = 1500;
				break;
			case $pts >= 12:
				$mute = 1200;
				break;
			case $pts >= 7:
				$mute = 900;
				break;
			case $pts >= 4:
				$mute = 300;
				break;
		}
		if(isset($ban)){
			$this->banPlayer($issuer, TextFormat::GOLD . "You have accumulated $pts warning points and have been banned for " . TextFormat::BLUE . ($ban / 86400) . TextFormat::GOLD . " days." . (($lastWarning !== null) ? (" Your latest warning: " . $lastWarning->toString()) : ""), $ban);
			return;
		}
		if(isset($mute)){
			$this->sesList->mutedIps[$this->getPlayer()->getAddress()] = $mi = new MuteIssue();
			$mi->reason = "Accumulated $pts warning points";
			$mi->issuer = $issuer;
			$mi->target = $this->getPlayer()->getAddress();
			$mi->from = time();
			$mi->duration = $mute;
			$mi->till = time() + $mute;
			$mi->notify("you", TextFormat::GOLD);
		}
	}

	public function getMain(){
		return $this->main;
	}
	public function getPlayer(){
		return $this->player;
	}
	public function getUID(){
		return $this->uid;
	}
	public function isLoggedIn(){
		return $this->authenticated;
//		return ($this->session & 0b11110000) === self::SES_STATE_GAME;
	}
	public function getRank(){
		return $this->mysqlSession->getRank();
	}
	public function getImportanceRank(){
		return $this->getRank() & Settings::RANK_SECTOR_IMPORTANCE;
	}
	public function getPrimaryPermRank(){
		return $this->getRank() & Settings::RANK_SECTOR_PERMISSION;
	}
	public function isMod(){
		return ($this->getPrimaryPermRank() & Settings::RANK_PERM_MOD) > 0;
	}
	public function isAdmin(){
		return ($this->getPrimaryPermRank() & Settings::RANK_PERM_ADMIN) > 0;
	}
	public function isTrial(){
		return ($this->getRank() & Settings::RANK_PREC_TRIAL) === Settings::RANK_PREC_TRIAL;
	}
	public function isHead(){
		return ($this->getRank() & Settings::RANK_PREC_HEAD) === Settings::RANK_PREC_HEAD;
	}
	public function getTeamRank(){
		return $this->mysqlSession->data["teamrank"];
	}
	public function getCoins(){
		return $this->mysqlSession->getData()["coins"];
	}
	public function setCoins($coins){
		$this->mysqlSession->data["coins"] = $coins;
	}
	public function getWarningPoints(){
		return $this->mysqlSession->data["warnpts"];
	}
	public function addWarningPoints($pts){
		$this->mysqlSession->data["warnpts"] += $pts;
	}
	/**
	 * @param Game|LegionPE $game
	 * @return bool
	 */
	public function inSession($game){
		if($game instanceof LegionPE){
			return $this->session === self::SESSION_GAME_HUB;
		}
		if($game instanceof Game){
			return $this->session === $game->getSessionId();
		}
		throw new \InvalidArgumentException("Unsupported argument type " . (is_object($game) ? get_class($game):gettype($game)));
	}
	public function getGame(){
		return $this->main->getGame($this->session);
	}
	public function getTeam(){
		if($this->mysqlSession->data["tid"] !== -1){
			return $this->getMain()->getTeamManager()->getTeam($this->mysqlSession->data["tid"]);
		}
		return null;
	}
	public function isGrindingCoins(){
		return time() - $this->mysqlSession->data["lastgrind"] < Settings::getGrindDuration($this);
	}
	public function canActivateGrindCoins(&$leftTime = 0){
		$expiry = $this->mysqlSession->data["lastgrind"] + Settings::getGrindActivationWaiting($this);
		$leftTime = $expiry - time();
		return $leftTime <= 0;
	}

	public static function offset(Player $player){
		return $player->getID();
	}
	public static function hash($string, $salt = self::SALT){
		return bin2hex(hash("sha512", $string . $salt, true) ^ hash("whirlpool", $salt . $string, true));
	}
	public static function checkHash($string, $salt, $realHash){
		$tmpHash = self::hash($string, $salt);
		return $tmpHash === $realHash;
	}

	public function isMuted(){
		if(isset($this->sesList->mutedIps[$ip = $this->getPlayer()->getAddress()])){
			$time = $this->sesList->mutedIps[$ip]->till;
			if(time() > $time){
				unset($this->sesList->mutedIps[$ip]);
				return false;
			}
			return true;
		}
		return false;
	}
	/**
	 * @param $seconds
	 *
	 * @deprecated
	 */
	public function mute($seconds){
//		$this->sesList->mutedIps[$this->getPlayer()->getAddress()] = microtime(true) + $seconds;
	}
	public function unmute(){
		if(isset($this->sesList->mutedIps[$ip = $this->getPlayer()->getAddress()])){
			unset($this->sesList->mutedIps[$ip]);
		}
	}
	public function subscribeToChannel(Channel $channel){
		$channel->subscribe($this);
	}
	public function unsubscribeFromChannel(Channel $channel){
		$channel->unsubscribe($this);
	}
	public function getID(){
		return $this->getPlayer()->getName();
	}
	public function isOper(){
		return ($this->getRank() & Settings::RANK_PERM_MOD) !== 0;
	}
	public function formatChat($msg, $isACTION = false){
		if(($this->session & self::SES_STATE_GAME) !== 0 and $this->session !== self::SESSION_GAME_HUB){
			return $this->getMain()->getGame($this->session)->formatChat($this, $msg, $isACTION);
		}
		return $isACTION ? "* {$this->getPlayer()->getDisplayName()} $msg":"{$this->getPlayer()->getDisplayName()}: $msg";
	}
	public function isDeafTo(ChannelSubscriber $other){
		if($this->deaf){
			return true;
		}
		if($other instanceof ChannelSubscriber and strpos($this->ignoring, "," . $other->getID() . ",") !== false){
			return true;
		}
		return false;
	}
	/**
	 * @return bool
	 */
	public function isVerboseSub(){
		return $this->verboseSub;
	}
	public function __toString(){
		return $this->getPlayer()->getDisplayName();
	}
	public function getRealName(){
		return $this->getPlayer()->getName();
	}
	/**
	 * @return MysqlSession
	 */
	public function getMysqlSession(){
		return $this->mysqlSession;
	}
	/**
	 * @return boolean
	 */
	public function isKicked(){
		return $this->kicked;
	}
	/**
	 * @param bool $on
	 */
	public function setChat($on){
		$this->deaf = !$on;
	}
	/**
	 * @return bool
	 */
	public function isChatOn(){
		return !$this->deaf;
	}
	public function ignoreChannel($channel){
		if(isset($this->deafToChannels[$channel = strtolower($channel)])){
			return false;
		}
		$this->deafToChannels[$channel] = true;
		return true;
	}
	public function unignoreChannel($channel){
		if(!isset($this->deafToChannels[$channel = strtolower($channel)])){
			return false;
		}
		unset($this->deafToChannels[$channel]);
		return true;
	}
	public function isIgnoringChannel($channel){
		return isset($this->deafToChannels[strtolower($channel)]);
	}

	public function isInvisible(){
		return array_sum($this->invisibilityFactors) !== 0;
	}
	public function spawnToAll(){
		if($this->isInvisible()){
			return;
		}
		foreach($this->getPlayer()->getLevel()->getPlayers() as $p){
			if(!isset($this->invisibleTo[$this->sesList->getSession($p)->getUID()])){
				$this->getPlayer()->spawnTo($p);
			}
		}
	}
	public function setInvisible($reason){
		$wasInvisible = $this->isInvisible();
		$this->invisibilityFactors[$reason] = 1;
		if(!$wasInvisible){
			foreach($this->getPlayer()->getLevel()->getPlayers() as $p){
				if(!isset($this->invisibleTo[$this->sesList->getSession($p)->getUID()])){
//					$this->getPlayer()->despawnFrom($p);
				}
			}
		}
	}
	public function setVisible($reason){
		$wasInvisible = $this->isInvisible();
		$this->invisibilityFactors[$reason] = 0;
		if($wasInvisible and !$this->isInvisible()){
			foreach($this->getPlayer()->getLevel()->getPlayers() as $p){
				if(!isset($this->invisibleTo[$this->sesList->getSession($p)->getUID()])) {
					$this->getPlayer()->spawnTo($p);
				}
			}
		}
	}
	/**
	 * @return Channel
	 */
	public function getWriteToChannel(){
		return $this->writeToChannel;
	}
	/**
	 * @param Channel $writeToChannel
	 */
	public function setWriteToChannel($writeToChannel){
		$this->writeToChannel = $writeToChannel;
	}
	/**
	 * @return int
	 */
	public function getSessionId(){
		return $this->session;
	}
	public function getInventorySession(){
		return $this->invSession;
	}
	public function setInventorySession($sess){
		$game = $this->getGame();
		if($game instanceof Game and !$game->onInventoryStateChange($this, $this->getInventorySession(), $sess)){
			return false;
		}
		$this->invSession = $sess;
		return true;
	}
}
