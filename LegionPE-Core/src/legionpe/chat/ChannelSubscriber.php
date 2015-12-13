<?php

namespace legionpe\chat;

interface ChannelSubscriber{
	/**
	 * Returns whether the subscriber is deaf and won't listen to any chat from any channels, except the mandatory channel
	 * @param ChannelSubscriber|null $other
	 * @return bool
	 */
	public function isDeafTo(ChannelSubscriber $other);
	/**
	 * @return bool
	 */
	public function isVerboseSub();
	/**
	 * @return bool
	 */
	public function isMuted();
	/**
	 * @param $seconds
	 */
	public function mute($seconds);
	/**
	 * @return void
	 */
	public function unmute();
	/**
	 * API function: subclasses should subscribe to Channel in the implementation of this function!
	 * @param Channel $channel
	 * @return void
	 */
	public function subscribeToChannel(Channel $channel);
	/**
	 * API function: subclasses should unsubscribe from Channel in the implementation of this function!
	 * @param Channel $channel
	 * @return void
	 */
	public function unsubscribeFromChannel(Channel $channel);
	/**
	 * Returns a unique identifier of the object
	 * @return string
	 */
	public function getID();
	/**
	 * Send to the subscriber a message
	 * @param string $msg a string message, could be formatted in the sprintf() method
	 * @param string ...$args arguments to be formatted in sprintf()
	 * @return void
	 */
	public function tell($msg, ...$args);
	/**
	 * Returns whether the subscriber has operator status.<br>
	 * Operator refers to the people who are in charge and have power to monitor chatting.
	 * @return bool
	 */
	public function isOper();
	/**
	 * @return \legionpe\LegionPE
	 */
	public function getMain();
	/**
	 * @param string $msg
	 * @param bool $isACTION
	 * @return string
	 */
	public function formatChat($msg, $isACTION = false);
	/**
	 * This method should return the display name of the sender.<br>
	 * It should preferrably be same as {@link ChannelSubscriber#getID()}.
	 * @return string
	 */
	public function __toString();
}
