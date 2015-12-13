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

use pocketmine\plugin\Plugin;
use pocketmine\scheduler\PluginTask;

class ReportCountTask extends PluginTask{
	/** @var \mysqli */
	private $mysql;
	public $connects, $joins;
	public function __construct(Plugin $plugin){
		parent::__construct($plugin);
		$this->mysql = new \mysqli("127.0.0.1", "root", "", "legionpe");
	}
	public function onRun($currentTick){
		$cnt = count($this->getOwner()->getServer()->getOnlinePlayers());
		$result = $this->mysql->query("SELECT unix_timestamp()-max(unix_timestamp(at))as m FROM players_cnt");
		$row = $result->fetch_assoc();
		$result->close();
		$m = is_array($row) ? (int) $row["m"] : 0;
		if($m === 0 or $m > 570){
			$this->mysql->query("DELETE FROM players_cnt WHERE unix_timestamp()-unix_timestamp(at) > 604800");
			$this->mysql->query("INSERT INTO players_cnt (connects,joins,cnt) VALUES ($this->connects,$this->joins,$cnt)");
			$this->connects = 0;
			$this->joins = 0;
			$warnings = $this->mysql->get_warnings();
			if($warnings instanceof \mysqli_warning) {
				do{
					echo $warnings->message;
				}while($warnings->next());
			}
		}
	}
}
