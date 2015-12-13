<?php

namespace legionpe;

use legionpe\chat\Channel;
use legionpe\chat\ChannelManager;
use legionpe\chat\ChannelSubscriber;
use legionpe\command\chan\ChanCurrentSubcommand;
use legionpe\command\chan\ChanJoinSubcommand;
use legionpe\command\chan\ChanKickSubcommand;
use legionpe\command\chan\ChanListSubcommand;
use legionpe\command\chan\ChanModularSubcommand;
use legionpe\command\chan\ChanQuitSubcommand;
use legionpe\command\chan\ChanSubscribingSubcommand;
use legionpe\command\chan\ChanSwitchSubcommand;
use legionpe\command\chan\ChanTeamSubcommand;
use legionpe\command\DirectTpCommand;
use legionpe\command\GetPosCommand;
use legionpe\command\info\InfoSessionSubcommand;
use legionpe\command\info\InfoUidSubcommand;
use legionpe\command\inv\InventoryChooseGameSubcommand;
use legionpe\command\inv\InventoryNormalizationSubcommand;
use legionpe\command\MStatusCommand;
use legionpe\command\MuteCommand;
use legionpe\command\PhpCommand;
use legionpe\command\RulesCommand;
use legionpe\command\session\AuthCommand;
use legionpe\command\session\ChatCommand;
use legionpe\command\session\CoinsCommand;
use legionpe\command\session\DisguiseCommand;
use legionpe\command\session\EvalCommand;
use legionpe\command\session\GrindCoinCommand;
use legionpe\command\session\IgnoreCommand;
use legionpe\command\session\MyKickCommand;
use legionpe\command\session\MysqlBanCommand;
use legionpe\command\session\oldSessionCommand;
use legionpe\command\session\QuitCommand;
use legionpe\command\session\StatisticsCommand;
use legionpe\command\session\TagCommand;
use legionpe\command\session\TeleportWorldCommand;
use legionpe\command\session\UnignoreCommand;
use legionpe\command\sublib\SubcommandMap;
use legionpe\command\team\TeamCreateSubcommand;
use legionpe\command\team\TeamInfoSubcommand;
use legionpe\command\team\TeamJoinSubcommand;
use legionpe\command\team\TeamKickSubcommand;
use legionpe\command\team\TeamListSubcommand;
use legionpe\command\team\TeamMembersSubcommand;
use legionpe\command\team\TeamPropertyChangeSubcommand;
use legionpe\command\team\TeamPublicitySubcommand;
use legionpe\command\team\TeamQuitSubcommand;
use legionpe\command\team\TeamRankChangeSubcommand;
use legionpe\command\UnmuteCommand;
use legionpe\command\warning\WarningCommand;
use legionpe\config\Settings;
use legionpe\games\kitpvp\PvpGame;
use legionpe\games\parkour\ParkourGame;
use legionpe\games\spleef\SpleefGame;
use legionpe\session\Session;
use legionpe\session\SessionInterface;
use legionpe\session\Warning;
use legionpe\team\TeamManager;
use legionpe\utils\CallbackPluginTask;
use legionpe\utils\Statistics;
use pocketmine\command\defaults\TimingsCommand;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\TimingsHandler;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\MainLogger;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

define("IS_TEST", is_file("__LEGIONPE_TEST_ENVIRONMENT__"));
if(IS_TEST){
	echo "Test environment identifier file detected.", PHP_EOL;
}
define("IT_IS_EASTER", false);
define("HAVE_THEY_FIXED_ARMOR", false);

class LegionPE extends PluginBase implements ChannelSubscriber{
	const TITLE_LEGIONPE_JOINS = "Number of joins of LegionPE";
	const TITLE_KITPVP_JOINS = "Number of joins of KitPvP";
	const TITLE_PARKOUR_JOINS = "Number of joins of Parkour";
	const TITLE_INFECTED_JOINS = "Number of joins of Infected";
	const TITLE_LEGIONPE_NEW_JOINS = "Number of new players registered on LegionPE";
	const TITLE_KITPVP_NEW_JOINS = "Number of new players registered on KitPvP";
	const TITLE_PARKOUR_NEW_JOINS = "Number of new players registered on Parkour";
	const TITLE_INFECTED_NEW_JOINS = "Number of new players registered on Infected";
	const IRC_WEBHOOK = "http://n.tkte.ch/h/3621/***?payload=";
	/** @var MysqlConnection */
	private $mysqli;
	/** @var SessionInterface */
	private $sessions;
	/** @var games\Game[] */
	private $games = [];
	/** @var Statistics */
	private $stats;
	private $words = null;
	/** @var TeamManager */
	private $teamMgr;
	/** @var bool */
	private $deaf = false;
	/** @var Channel */
	private $hubChannel, $mandatoryChannel, $supportChannel, $staffChannel;
	/** @var ChannelManager */
	private $chanMgr;
	private $verboseSub = false;
	private $timestamp = 0;
	/** @var mixed[] */
	private $localize;
	//	/** @var IRCSender */
//	private $ircSender;
	public function onLoad(){
		Utils::getIP();
		$this->saveResource("localize.yml");
	}
	public function onEnable(){
//		$this->ircSender = new IRCSender;
//		$this->ircSender->start();
//		$this->getLogger()->addAttachment($att = new IRCLoggerAttachment($this->ircSender));
		echo "Enabling auto save...";
		$this->getServer()->setAutoSave(true);
		$this->resetLine();
		echo "Preloading resources...";
		$this->words = json_decode(stream_get_contents($stream = $this->getResource("words.json")), true);
		$this->timestamp = (int) stream_get_contents($stream = $this->getResource("timestamp.LEGIONPE"));
		fclose($stream);
		$this->resetLine();
		echo "Establishing MySQL connection...";
		$this->mysqli = new MysqlConnection($this);
		$this->resetLine();
		echo "Initializing chat channels...";
		$this->chanMgr = new ChannelManager($this);
		$this->hubChannel = $this->chanMgr->joinChannel($this, "Global", Channel::CLASS_MODULAR, false);
		$this->mandatoryChannel = $this->chanMgr->joinChannel($this, "Mandatory", Channel::CLASS_INFRASTRUCTURAL, true);
		$this->supportChannel = $this->chanMgr->joinChannel($this, "Support", Channel::CLASS_CUSTOM, true);
		$this->staffChannel = $this->chanMgr->joinChannel($this, "Staff", Channel::CLASS_CUSTOM);
		$this->resetLine(true);
		echo "Initializing teams...";
		$this->teamMgr = new TeamManager($this);
		$this->resetLine();
		echo "Registering sessions handler and commands...";
		$this->getServer()->getPluginManager()->registerEvents($this->sessions = new SessionInterface($this), $this);
		$this->registerCommands();
		if(!HAVE_THEY_FIXED_ARMOR){
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new CallbackPluginTask($this, function(){
				foreach($this->getServer()->getOnlinePlayers() as $p){
					if($p->isOnline()){
						$p->getInventory()->sendArmorContents($p->getLevel()->getPlayers());
					}
				}
			}), 20, 20);
		}
		$this->resetLine();
		echo "Initializing statistics...";
		$this->stats = new Statistics([
			self::TITLE_LEGIONPE_JOINS,
			self::TITLE_KITPVP_JOINS,
			self::TITLE_PARKOUR_JOINS,
			self::TITLE_INFECTED_JOINS,
			self::TITLE_LEGIONPE_NEW_JOINS,
			self::TITLE_KITPVP_NEW_JOINS,
			self::TITLE_PARKOUR_NEW_JOINS,
			self::TITLE_INFECTED_NEW_JOINS,
		], $this);
		$this->initModules();
		$this->resetLine(true);
		$msg = $this->getDescription()->getFullName() . " has been enabled at " . Utils::getIP() . ":{$this->getServer()->getPort()}. ";
		$msg .= "The process ID is " . getmypid() . ". ";
		$dateTime = (new \DateTime)->setTimestamp($this->timestamp);
		$msg .= "The plugin was built on " . $dateTime->format("jS F, Y \\a\\t H:i:s (T, \\G\\M\\T P). ");
		$msg .= "Easter mode is " . (IT_IS_EASTER ? "":"not ") . "enabled.";
		$msg .= "Timings is now being enabled.";
		if(!IS_TEST){
			Utils::getURL(self::IRC_WEBHOOK . urlencode($msg), 2);
		}
		$this->localize = yaml_parse_file($this->getDataFolder() . "localize.yml");
		if(!IS_TEST){
			$sid = $this->localize["server-id"];
			$ip = $this->localize["ip"];
			$port = $this->localize["port"];
			$this->getMySQLi()->query("INSERT INTO active_servers(sid,address,port,pid)VALUES($sid,%s,$port,%s)ON DUPLICATE KEY UPDATE last_up=NOW(),pid=%s", MysqlConnection::RAW, $ip, (string) getmypid(), (string) getmypid());
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new CallbackPluginTask($this, function() use($sid, $port, $ip){
				$total = count($this->getServer()->getOnlinePlayers());
				$kitpvp = $this->getGame(Session::SESSION_GAME_KITPVP)->countPlayers();
				$parkour = $this->getGame(Session::SESSION_GAME_PARKOUR)->countPlayers();
				$spleef = $this->getGame(Session::SESSION_GAME_SPLEEF)->countPlayers();
				$this->getMySQLi()->query("UPDATE active_servers SET last_up=NOW(),cnt=$total,kitpvp=$kitpvp,parkour=$parkour,spleef=$spleef WHERE sid=$sid", MysqlConnection::RAW, $ip);
			}), 50, 50);
		}
		$this->getLogger()->info($msg);
		$this->getServer()->getPluginManager()->setUseTimings(true);
		TimingsHandler::reload();
	}
	private function registerCommands(){
		$this->getServer()->getCommandMap()->registerAll("l", [
			new AuthCommand($this),
			new SubcommandMap($this, "channel", "Manage chat channels", "/ch help", ["ch", "chan"], [
				new ChanCurrentSubcommand,
				new ChanJoinSubcommand,
				new ChanKickSubcommand,
				new ChanListSubcommand,
				new ChanModularSubcommand,
				new ChanQuitSubcommand,
				new ChanSubscribingSubcommand,
				new ChanSwitchSubcommand,
				new ChanTeamSubcommand,
			]),
			new ChatCommand($this),
			new CoinsCommand($this),
			new DisguiseCommand($this),
			new DirectTpCommand($this),
			new EvalCommand($this),
			new GetPosCommand($this),
			new GrindCoinCommand($this),
			new IgnoreCommand($this),
			new UnignoreCommand($this),
			new SubcommandMap($this, "info", "Show information", "/info help", ["information"], [
				new InfoSessionSubcommand,
				new InfoUidSubcommand,
			]),
			new SubcommandMap($this, "inventory", "Change inventory type", "/inv help", ["inv"], [
				new InventoryNormalizationSubcommand,
				new InventoryChooseGameSubcommand,
			]),
			new MysqlBanCommand($this),
			new MyKickCommand($this),
			new MuteCommand($this),
			new UnmuteCommand($this),
			new MStatusCommand($this),
			new PhpCommand($this),
			new QuitCommand($this),
			new RulesCommand($this),
			new StatisticsCommand($this),
			new TagCommand($this),
			new SubcommandMap($this, "team", "Manage teams", "/team help", ["t"], [
				new TeamCreateSubcommand,
				new TeamJoinSubcommand,
				new TeamKickSubcommand,
				new TeamQuitSubcommand,
				new TeamRankChangeSubcommand(true), // /team promote
				new TeamRankChangeSubcommand(false), // /team demote
				new TeamMembersSubcommand,
				new TeamPublicitySubcommand(true), // /team open
				new TeamPublicitySubcommand(false), // /team close
				TeamPropertyChangeSubcommand::requires(), // /team req
				TeamPropertyChangeSubcommand::rules(), // /team rul
				new TeamInfoSubcommand,
				new TeamListSubcommand,
			]),
			new TeleportWorldCommand($this),
			new WarningCommand($this, "ad", "advertizing", Warning::CLASS_ADS),
			new WarningCommand($this, "cap", "using abusive caps", Warning::CLASS_CAPS),
			new WarningCommand($this, "do", "disobeying staffs instructions", Warning::CLASS_DISOBEDIENCE),
			new WarningCommand($this, "impose", "staff imposing", Warning::CLASS_IMPOSE),
			new WarningCommand($this, "misc", "miscellaneous reasons", Warning::CLASS_MISC),
			new WarningCommand($this, "mod", "using mods", Warning::CLASS_MODS),
			new WarningCommand($this, "mspam", "small-scale spamming including not following chat flooding control, equivalent to abusive caps warnings", Warning::CLASS_SMALL_SPAM),
			new WarningCommand($this, "spam", "large-scale spamming", Warning::CLASS_SPAM),
			new WarningCommand($this, "swear", "using inappropriate language", Warning::CLASS_SWEAR),
		]);
	}
	private function initModules(){
		echo "Initializing levels...";
		$this->resetLine(true);
		Settings::init($this->getServer());
		echo "Initializing module: " . "KitPvP";
		$this->games[Session::SESSION_GAME_KITPVP] = new PvpGame($this);
		$this->resetLine();
		echo "Initializing module: " . TextFormat::AQUA . "Parkour\r" . TextFormat::WHITE;
		$this->games[Session::SESSION_GAME_PARKOUR] = new ParkourGame($this);
		$this->resetLine();
		echo "Initializing module: " . TextFormat::AQUA . "Spleef\r" . TextFormat::WHITE;
		$this->games[Session::SESSION_GAME_SPLEEF] = new SpleefGame($this);
		$this->resetLine();
//		echo "Initializing module: " . TextFormat::AQUA . "Infected\r" . TextFormat::WHITE;
//		$this->games[Session::SESSION_GAME_INFECTED] = new InfectedGame($this);
//		$this->resetLine();
		echo "Registering minigame commands...";
		$this->resetLine();
		$cmds = [];
		foreach($this->games as $game){
			foreach($game->getDefaultCommands() as $cmd){
				if(!isset($cmd["aliases"])){
					$cmd["aliases"] = [];
				}
				$cmd["game"] = $game->getName();
				if(isset($cmds[$cmd["name"]])){
					$cmd["description"] .= "\n" . $cmds[$cmd["name"]]["description"];
					$cmd["usage"] .= "\n" . $cmds[$cmd["name"]]["usage"];
					$cmd["aliases"] = array_merge($cmd["aliases"], $cmds[$cmd["name"]]["aliases"]);
				}
				$cmds[$cmd["name"]] = $cmd;
			}
		}
		foreach($cmds as $cmd){
			$this->getServer()->getCommandMap()->register("legionpe", new oldSessionCommand($this, $cmd["name"], $cmd["description"], $cmd["usage"], $cmd["aliases"]));
		}
	}
	private function resetLine($clear = false){
		echo "\r";
		if($clear){
			echo str_repeat(" ", 100) . "\r";
		}
	}
	public function onDisable(){
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->sessions->onPlayerDisconnect(new PlayerQuitEvent($player, "Server stop"));
		}
		$this->teamMgr->saveTeams();
		$this->mysqli->close();
		$url = $this->pasteTimings();
		$mem = (memory_get_usage(true) / 1048576) . "MB";
		$msg = $this->getDescription()->getFullName() . " has been disabled at " . Utils::getIP() . ":{$this->getServer()->getPort()} with peak memory reaching $mem. ";
		$msg .= "The process ID is " . getmypid() . ". ";
		$dateTime = (new \DateTime)->setTimestamp($this->timestamp);
		$msg .= "The plugin was built on " . $dateTime->format("jS F, Y \\a\\t H:i:s (T, \\G\\M\\T P). ");
		$msg .= "Timings has been pasted to $url.";
		if(!IS_TEST){
			Utils::getURL(self::IRC_WEBHOOK . urlencode($msg), 2);
		}
		$this->getLogger()->info($msg);
	}

	public function getGame($game){
		$game |= Session::SES_STATE_GAME;
		return isset($this->games[$game]) ? $this->games[$game]:null;
	}
	public function getGames(){
		$games = $this->games;
		return $games;
	}
	public function updateGameSessionData(){
		foreach($this->games as $game){
			$game->saveSessionsData();
		}
	}
	/**
	 * @return MysqlConnection
	 */
	public function getMySQLi(){
		return $this->mysqli;
	}
	public function getDefaultChannel(){
		return $this->hubChannel;
	}
	public function getSessions(){
		return $this->sessions;
	}
	public function getWordsJSON(){
		return $this->words;
	}
	public function securityAppend($line, ...$args){
		touch($fn = "security-logs.log");
		file_put_contents($fn, sprintf($line, ...$args) . PHP_EOL, FILE_APPEND);
//		if(!is_resource($this->securityLogs)){
//			$this->securityLogs = fopen("security-logs.log", "at");
//		}
//		fwrite($this->securityLogs, "$line\n"); // the "t" mode should convert it.
	}
	public function getTeamManager(){
		return $this->teamMgr;
	}

	/**
	 * @return boolean
	 */
	public function isMuted(){
		return false;
	}
	public function mute($seconds){
	}
	public function unmute(){
	}
	/**
	 * API function: subclasses should subscribe to Channel in the implementation of this function!
	 * @param Channel $channel
	 * @return void
	 */
	public function subscribeToChannel(Channel $channel){
		$channel->subscribe($this);
	}
	/**
	 * API function: subclasses should unsubscribe from Channel in the implementation of this function!
	 * @param Channel $channel
	 * @return void
	 */
	public function unsubscribeFromChannel(Channel $channel){
		$channel->unsubscribe($this);
	}
	/**
	 * Returns a unique identifier of the object
	 * @return string
	 */
	public function getID(){
		return "Legion PE";
	}
	/**
	 * Send to the subscriber a message
	 * @param string $msg a string message, could be formatted in the sprintf() method
	 * @param string ...$args arguments to be formatted in sprintf()
	 * @return void
	 */
	public function tell($msg, ...$args){
		try{
			MainLogger::getLogger()->info(sprintf($msg, ...$args));
		}
		catch(\RuntimeException $e){
			var_dump($msg, $args, $e);
		}
	}
	public function isOper(){
		return true;
	}
	public function getMain(){
		return $this;
	}
	public function formatChat($msg, $isACTION = false){
		return $isACTION ? "* LegionPE $msg":"[LegionPE] $msg";
	}
	public function isDeafTo(ChannelSubscriber $subscriber){
		return $this->deaf;
	}
	/**
	 * @return ChannelManager
	 */
	public function getChannelManager(){
		return $this->chanMgr;
	}
	public function getMandatoryChannel(){
		return $this->mandatoryChannel;
	}
	public function getSupportChannel(){
		return $this->supportChannel;
	}
	public function getStaffChannel(){
		return $this->staffChannel;
	}
	public function isVerboseSub(){
		return $this->verboseSub;
	}
	public function __toString(){
		return "Legion PE";
	}
	/**
	 * @return Statistics
	 */
	public function getStats(){
		return $this->stats;
	}
	public function evaluate($code){
		eval($code);
	}
	public function __debugInfo(){
		return [];
	}
	public function setSpawnOnPlayer(Player $player){
		$player->setSpawn(Settings::loginSpawn($this->getServer()));
	}
	private function pasteTimings(){
		$sampleTime = microtime(true) - TimingsCommand::$timingStart;
		$ft = fopen("php://temp", "r+b");
		TimingsHandler::printTimings($ft);
		fwrite($ft, "Sample time " . round($sampleTime * 1000000000) . " (" . $sampleTime . "s)" . PHP_EOL);
		fseek($ft, 0);
		$data = ["syntax" => "text", "poster" => "LegionPE", "content" => stream_get_contents($ft)];
		$ch = curl_init("http://paste.ubuntu.com/");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["User-Agent: LegionPE " . $this->getDescription()->getVersion()]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = curl_exec($ch);
		curl_close($ch);
		if(preg_match('#^Location: http://paste\\.ubuntu\\.com/([0-9]{1,})/#m', $data, $matches) == 0){
			return "about:blank";
		}
		fclose($ft);
		return "http://timings.aikar.co/?url=$matches[1]";
	}
}
