<?php

namespace legionpe\games\spleef;

use pocketmine\block\Block;
use pocketmine\level\Location;
use pocketmine\math\Vector3;

class SpleefArenaConfig{
	public $name;
	/** @var Location */
	public $spectatorSpawnLoc; // ssl? xD
	/** @var Location */
	public $playerPrepLoc;
	/** @var Location[] */
	public $playerStartLocs = [];
	/** @var int */
	public $fromx, $tox, $fromz, $toz;
	/** @var int */
	public $floors;
	/** @var int includes the floor layer */
	public $floorHeight;
	/** @var int */
	public $lowestY;
	/** @var Block[] */
	public $floorMaterials;
	/** @var \pocketmine\item\Item[][] */
	public $playerItems;
	/** @var int */
	public $minPlayers;
	/** @var int */
	public $minWaitTicks;
	/** @var int */
	public $maxWaitTicks;
	/** @var int */
	public $maxGameTicks;
	public function getMaxPlayers(){
		return count($this->playerStartLocs);
	}
	public function build(){
		$materialMaxKey = count($this->floorMaterials) - 1;
		$level = $this->playerPrepLoc->getLevel();
//		$level->getServer()->getLogger()->debug("Start rebuilding of spleef $this->name");
		for($floor = 0; $floor < $this->floors; $floor++){
			$y = $this->lowestY + $floor * $this->floorHeight;
			for($x = $this->fromx; $x <= $this->tox; $x++){
				for($z = $this->fromz; $z <= $this->toz; $z++){
					$level->setBlock(new Vector3($x, $y, $z), $this->floorMaterials[mt_rand(0, $materialMaxKey)], false, false);
				}
			}
		}
//		$level->getServer()->getLogger()->debug("Finished rebuilding of spleef $this->name");
	}
}
