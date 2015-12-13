<?php

/**
 * LegionPE
 * Copyright (C) 2015 PEMapModder
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace counter;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\Info;
use pocketmine\plugin\PluginBase;

class Counter extends PluginBase implements Listener{
	/** @var ReportCountTask */
	private $task;
	public function onEnable(){
		$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask($this->task = new ReportCountTask($this), 1200, 1200);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	/**
	 * @param PlayerJoinEvent $event
	 * @priority MONITOR
	 * @ignoreCancelled true
	 */
	public function onJoin(/** @noinspection PhpUnusedParameterInspection */ PlayerJoinEvent $event){
		$this->task->joins++;
	}
	/**
	 * @param DataPacketReceiveEvent $event
	 * @priority MONITOR
	 * @ignoreCancelled true
	 */
	public function onPacketReceived(DataPacketReceiveEvent $event){
		if($event->getPacket()->pid() === Info::LOGIN_PACKET){
			$this->task->connects++;
		}
	}
}
