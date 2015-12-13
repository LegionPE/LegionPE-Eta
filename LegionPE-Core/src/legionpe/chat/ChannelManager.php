<?php

namespace legionpe\chat;

use legionpe\LegionPE;
use legionpe\utils\CallbackPluginTask;

class ChannelManager{
	private $main;
	/** @var Channel[] */
	private $channels = [];
	public function __construct(LegionPE $main){
		$this->main = $main;
		$this->main->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackPluginTask($this->main, array($this, "tickClean")), 1200); // invoke this method if needed
	}
	/**
	 * @param ChannelSubscriber $subscriber
	 * @param string $name
	 * @param int $class
	 * @param bool $canFreeJoin
	 * @param bool $checkFreeJoin
	 * @return Channel|false Channel object for the channel trying to join, or false if cannot join
	 */
	public function joinChannel(ChannelSubscriber $subscriber, $name, $class, $canFreeJoin = false, $checkFreeJoin = false){
		if($this->hasChannel($name)){
			$ch = $this->getChannel($name);
			if($checkFreeJoin and !$ch->canFreeJoin()){
				return false;
			}
			$subscriber->subscribeToChannel($this->getChannel($name));
		}
		else{
			$this->addChannel($ch = new Channel($name, $subscriber, $class, $canFreeJoin));
		}
		return $ch;
	}
	public function tickClean(){
		foreach($this->channels as $key => $channel){
			if(($emptySince = $channel->emptySince()) === false){
				continue;
			}
			if(abs(time() - $emptySince) > 60){
				unset($this->channels[$key]);
			}
		}
	}
	public function quit(ChannelSubscriber $subscriber){
		foreach($this->channels as $channel){
			if($channel->isSubscribing($subscriber)){
				$subscriber->unsubscribeFromChannel($channel);
			}
		}
	}
	public function hasChannel($name){
		return isset($this->channels[strtolower($name)]);
	}
	public function getChannel($name){
		return $this->hasChannel($name) ? $this->channels[strtolower($name)]:false;
	}
	public function addChannel(Channel $channel){
		$this->channels[strtolower($channel->getName())] = $channel;
	}
	public function listChannels(){
		return $this->channels;
	}
}
