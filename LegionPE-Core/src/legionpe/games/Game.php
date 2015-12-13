<?php

namespace legionpe\games;

use legionpe\session\Session;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;

interface Game extends Listener{
	/**
	 * @return \legionpe\LegionPE
	 */
	public function getMain();
	/**
	 * @return string
	 */
	public function getName();
	/**
	 * @return \pocketmine\item\Item
	 */
	public function getSelectionItem();
	/**
	 * @param Session $session
	 * @return bool allow join
	 */
	public function onJoin(Session $session);
	/**
	 * @param Session $session
	 * @param bool $force
	 * @return bool allow quit or $force
	 */
	public function onQuit(Session $session, $force);
	/**
	 * @return int
	 */
	public function getSessionId();
	/**
	 * @return \legionpe\chat\Channel
	 */
	public function getDefaultChatChannel();
	/**
	 * @return array
	 */
	public function getDefaultCommands();
	/**
	 * This method will only get called when $session {@link Game::onJoin()}'ed the game
	 * and hasn't {@link Game#onQuit()}'ed yet.<br>
	 * Gracefully throw a {@link \RuntimeException} if it is
	 * @param Session $session
	 * @param string $msg
	 * @param bool $isACTION
	 * @return string
	 */
	public function formatChat(Session $session, $msg, $isACTION);
	/**
	 * @param Session $session
	 * @param string[] $args
	 * @return string
	 */
	public function onStats(Session $session, array $args);
	/**
	 * @param Command $cmd
	 * @param Session $session
	 * @return bool
	 */
	public function testCommandPermission(Command $cmd, Session $session);
	/**
	 * @param Command $cmd
	 * @param array $args
	 * @param Session $session
	 */
	public function onCommand(Command $cmd, array $args, Session $session);
	public function onMove(Session $session, PlayerMoveEvent $event);
	public function onRespawn(PlayerRespawnEvent $event, Session $session);
	/**
	 * @param Session $ses
	 * @param int $from
	 * @param int $to
	 * @return bool
	 */
	public function onInventoryStateChange(Session $ses, $from, $to);
	public function saveSessionsData();
	/**
	 * @return int
	 */
	public function countPlayers();
	public function __toString();
	public function onDisable();
}
