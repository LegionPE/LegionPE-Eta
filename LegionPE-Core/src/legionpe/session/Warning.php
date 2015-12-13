<?php

namespace legionpe\session;

use legionpe\MysqlConnection;
use pocketmine\utils\TextFormat;

class Warning{
	const CLASS_MISC = 0;
	const CLASS_MODS = 1;
	const CLASS_SPAM = 2;
	const CLASS_IMPOSE = 3;
	const CLASS_SWEAR = 4;
	const CLASS_ADS = 5;
	const CLASS_CAPS = 6;
	const CLASS_SMALL_SPAM = 7;
	const CLASS_DISOBEDIENCE = 8;
	public static $WARNINGS_POINTS = [
		self::CLASS_MODS => 20,
		self::CLASS_SPAM => 16,
		self::CLASS_IMPOSE => 12,
		self::CLASS_SWEAR => 7,
		self::CLASS_ADS => 7,
		self::CLASS_CAPS => 4,
		self::CLASS_SMALL_SPAM => 4,
		self::CLASS_DISOBEDIENCE => 1,
	];
	public static $WARNINGS_MESSAGES = [
		self::CLASS_MODS => "client mods are disallowed in LegionPE",
		self::CLASS_SPAM => "spamming is against the /rules",
		self::CLASS_IMPOSE => "staff imposing is against the /rules",
		self::CLASS_SWEAR => "using inappropriate language is against the /rules",
		self::CLASS_ADS => "advertizing is against the /rules",
		self::CLASS_CAPS => "using abusive caps is disallowed",
		self::CLASS_SMALL_SPAM => "spamming is against the /rules",
		self::CLASS_DISOBEDIENCE => "please follow the instructions from staff members",
	];
	private $id = null;
	private $uid;
	public $class;
	public $pts = 0;
	public $msg = "";
	public $issuer = "unknown issuer";
	public $creation;
	/**
	 * @param int $uid
	 * @param int $class
	 * @param int $pts
	 * @param string $msg
	 * @param string $issuer
	 * @param int|null $creation
	 * @param int|null $id
	 */
	public function __construct($uid, $class = self::CLASS_MISC, $pts = 0, $msg = "", $issuer = "unknown issuer", $creation = null, $id = null){
		$this->id = $id;
		$this->uid = $uid;
		$this->pts = $pts;
		$this->msg = $msg;
		$this->issuer = $issuer;
		$this->creation = ($creation === null) ? time() : $creation;
	}
	/**
	 * @param MysqlConnection $mysql
	 */
	public function insert(MysqlConnection $mysql){
		if($this->id !== null){
			throw new \RuntimeException("Warning already inserted");
		}
		$mysql->query("LOCK TABLES warnings_logs WRITE", MysqlConnection::RAW);
		$mysql->query("INSERT INTO warnings_logs(uid,class,pts,msg,issuer,creation)VALUES($this->uid,$this->class,$this->pts,%s,%s,from_unixtime($this->creation))", MysqlConnection::RAW, $this->msg, $this->issuer);
		$this->id = $mysql->query("SELECT id FROM warnings_logs ORDER BY id DESC LIMIT 1", MysqlConnection::ASSOC)["id"];
		$mysql->query("UNLOCK TABLES", MysqlConnection::RAW);
	}
	public static function fromAssoc(array $data){
		return new self($data["uid"], $data["class"], $data["pts"], $data["msg"], $data["issuer"], $data["creation"], $data["id"]);
	}
	public static function fetchById(MysqlConnection $mysql, $id){
		return self::fromAssoc($mysql->query("SELECT uid,class,pts,msg,issuer,unix_timestamp(creation)AS creation,id FROM warnings_logs WHERE id=$id", MysqlConnection::ASSOC));
	}
	/**
	 * @return int|null
	 */
	public function getId(){
		return $this->id;
	}
	/**
	 * @return int
	 */
	public function getUid(){
		return $this->uid;
	}
	public function toString($normColor = TextFormat::GOLD){
		return $normColor . "Warning (Reference ID: #" . TextFormat::BLUE . $this->id . ") of " . TextFormat::BLUE . $this->pts . $normColor . " points from " . TextFormat::BLUE . $this->issuer . $normColor . " given on " . TextFormat::BLUE . date("M j, Y H:i:s") . $normColor . " with message: " . TextFormat::BLUE . $this->msg;
	}
}
