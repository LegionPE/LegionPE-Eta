<?php

namespace legionpe\utils;

use pocketmine\level\Level;
use pocketmine\math\Vector3;

class Cuboid{
	private $x1, $y1, $z1, $x2, $y2, $z2;
	/** @var Level */
	private $level;
	public function __construct(Vector3 $from, Vector3 $to, Level $l){
		$this->x1 = min($from->x, $to->x);
		$this->y1 = min($from->y, $to->y);
		$this->z1 = min($from->z, $to->z);
		$this->x2 = max($from->x, $to->x);
		$this->y2 = max($from->y, $to->y);
		$this->z2 = max($from->z, $to->z);
		$this->level = $l;
	}
}
