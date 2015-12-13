<?php

namespace legionpe\chat;

use legionpe\LegionPE;
use legionpe\session\Session;
use pocketmine\utils\TextFormat;

class Channel{
	const LEVEL_IMPORTANT_INFO = 3;
	const LEVEL_CHAT = 2;
	const LEVEL_VERBOSE_INFO = 1;
	/** Basic channel of a player; players should never automatically quit it */
	const CLASS_PERSONAL = 0;
	/** Infrastructural channel of the server; players should never automatically quit it */
	const CLASS_INFRASTRUCTURAL = 1;
	/** Modular channel, which changes when travelling among modules */
	const CLASS_MODULAR = 2;
	/** Team channel */
	const CLASS_TEAM = 3;
	/** Custom channel (not implemented yet) */
	const CLASS_CUSTOM = 4;
	/** @var string */
	private $name;
	/** @var ChannelSubscriber */
	private $owner;
	/** @var ChannelSubscriber[] */
	private $subscribers;
	private $class;
	private $canFreeJoin;
	private $emptySince;
	public function __construct($name, ChannelSubscriber $owner, $class, $canFreeJoin = false){
		$this->name = $name;
		$this->owner = $owner;
		$this->subscribers = [$owner->getID() => $owner];
		$this->class = $class;
		$this->canFreeJoin = $canFreeJoin;
	}
	public function getName(){
		return $this->name;
	}
	public function getLowName(){
		return strtolower($this->name);
	}
	public function subscribe(ChannelSubscriber $subscriber){
		$this->subscribers[$subscriber->getID()] = $subscriber;
		return $this;
	}
	public function canFreeJoin(){
		return $this->canFreeJoin;
	}
	public function isSubscribing(ChannelSubscriber $subscriber){
		return isset($this->subscribers[$subscriber->getID()]);
	}
	public function unsubscribe(ChannelSubscriber $subscriber){
		if(strtolower($this->name) === "mandatory"){
			return false;
		}
		unset($this->subscribers[$subscriber->getID()]);
		if(count($this->subscribers) === 0){
			$this->emptySince = time();
		}
		return true;
	}
	public function send(ChannelSubscriber $sender, $msg, $isACTION = false){
		$msg = str_replace("%", "%%", $msg);
		if(strtolower($this->name) === "mandatory"){
			$this->broadcast("[NOTICE] <$sender> $msg", self::LEVEL_IMPORTANT_INFO);
			return;
		}
		if($sender->isMuted()){
			$sender->tell("You were muted! You can't chat!");
			return;
		}
		$msg = $sender->formatChat($msg, $isACTION);
		if($this->class !== self::CLASS_MODULAR){
			$msg = "#$this>$msg";
		}
		$words = $sender->getMain()->getWordsJSON();
		$regex = sprintf($words["badword_detection_regexp"], implode("|", $words["badwords"]));
		if($count = preg_match_all($regex, $msg, $matches)){
			$sender->tell("Your message contains %d bad words: %s", $count, $implosion = implode(", ", $matches[$words["badword_regexp_parenthese_order"]]));
			$sender->getMain()->securityAppend("%s sent %d bad words (%s) in the message %s", $sender->getID(), $count, $implosion, $msg);
			if($sender instanceof Session){
				// TODO warn
			}
			return;
		}
		foreach($this->subscribers as $sub){
			if(!$sub->isDeafTo($sender)){
				$sub->tell($msg);
			}
		}
	}
	public function emptySince(){
		if(count($this->subscribers) !== 0){
			return false;
		}
		return $this->emptySince;
	}
	public function kick(ChannelSubscriber $kicker, ChannelSubscriber $target){
		if(!$kicker->isOper() and $kicker !== $this->owner){
			$kicker->tell(TextFormat::RED . "You don't have permission to kick a subscriber on channel #%s!", $this->name);
			return;
		}
		if($target instanceof LegionPE){
			$kicker->tell(TextFormat::RED . "You can't kick the server out of the channel!");
			return;
		}
		$target->unsubscribeFromChannel($this);
		if($target === $this->owner){
			$this->broadcast("The channel owner of #$this has been kicked. The owner of #$this is now Legion PE.", self::LEVEL_IMPORTANT_INFO);
			$this->owner = $this->owner->getMain();
		}
		$kicker->tell(TextFormat::GREEN . "$target has been kicked from #$this.");
	}
	public function broadcast($msg, $level = self::LEVEL_CHAT){
		$msg = str_replace("%", "%%", $msg);
		foreach($this->subscribers as $sub){
			if($level !== self::LEVEL_IMPORTANT_INFO and $sub->isDeafTo($sub->getMain())){
				continue;
			}
			if($level !== self::LEVEL_VERBOSE_INFO or $sub->isVerboseSub()){
				$sub->tell($msg);
			}
		}
	}
	public function __toString(){
		return $this->name;
	}
	public function getOwner(){
		return $this->owner;
	}
	public function getClass(){
		return $this->class;
	}
	public function getClassAsString(){
		switch($this->class){
			case self::CLASS_PERSONAL:
				return "personal";
			case self::CLASS_INFRASTRUCTURAL:
				return "infrastructural";
			case self::CLASS_MODULAR:
				return "modular";
			case self::CLASS_TEAM:
				return "team";
			case self::CLASS_CUSTOM:
				return "custom";
		}
		return "unknown";
	}
}
