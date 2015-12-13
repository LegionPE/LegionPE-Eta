<?php

namespace legionpe\games\kitpvp;

use legionpe\config\Settings;
use legionpe\MysqlConnection;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Bow;
use pocketmine\item\Item;
use pocketmine\item\Snowball;

class PvpKit{
	public $uid; // a
	public $kitid; // b
	public $name; // c
	public $helmet = 0; // d
	public $chestplate = 0; // e
	public $leggings = 0; // f
	public $boots = 0; // g
	public $weapon = 0; // h
	public $food = 0; // i
	public $arrows = 0; // j
	public $updated = false;
	public $insert = true;
	private function __construct($uid, $kitid){
		$this->uid = $uid;
		$this->kitid = $kitid;
		$this->name = "Kit $kitid";
	}
	public static function getDefault($uid, $kitid){
		return new self($uid, $kitid);
	}
	public static function fromAssoc(array $assoc){
		$obj = new self($assoc["uid"], $assoc["kitid"]);
		foreach($assoc as $key => $value){
			if($key !== "name"){
				$value = (int) $value;
			}
			elseif($value === ""){
				$value = "Kit " . $assoc["kitid"];
			}
			$obj->$key = $value;
		}
		$obj->insert = false;
		return $obj;
	}
	public function equip(PlayerInventory $inv, PvpSessionData $data, $send = true){
		$inv->clearAll();
		$messages = [];
		$info = Settings::kitpvp_getKitUpgradeInfo("helmet", $this->helmet);
		$inv->setHelmet($info->getItem());
		$messages[] = $info->itemsToString();
		$info = Settings::kitpvp_getKitUpgradeInfo("chestplate", $this->chestplate);
		$inv->setChestplate($info->getItem());
		$messages[] = $info->itemsToString();
		$info = Settings::kitpvp_getKitUpgradeInfo("leggings", $this->leggings);
		$inv->setLeggings($info->getItem());
		$messages[] = $info->itemsToString();
		$info = Settings::kitpvp_getKitUpgradeInfo("boots", $this->boots);
		$inv->setBoots($info->getItem());
		$messages[] = $info->itemsToString();
		$inv->sendArmorContents($inv->getViewers());
		if($data->isUsingBowKit()){
			$weapon = ($data->getBowLevel() > 0) ? new Bow:Item::get(Item::AIR);
			$messages[] = "a bow";
		}
		else{
			$info = Settings::kitpvp_getKitUpgradeInfo("weapon", $this->weapon);
			$weapon = $info->getItem();
			$messages[] = $info->itemsToString();
		}
		$info = Settings::kitpvp_getKitUpgradeInfo("food", $this->food);
		$food = $info->getItem();
		$messages[] = $info->itemsToString();
		$info = Settings::kitpvp_getKitUpgradeInfo("arrows", $this->arrows);
		$arrows = $info->getItem();
		$messages[] = $info->itemsToString();
		/** @var \pocketmine\item\Item[] $items */
		$items = [];
		if($weapon->getId() !== Item::AIR){
			$items[] = $weapon;
		}
		if($food->getId() !== 0){
			$items[] = $food;
		}
		if($arrows->getId() !== 0){
			$items[] = $arrows;
		}
		$inv->addItem(...$items);
		$cnt = Settings::easter_getSnowballCount($data->getSession());
		if($cnt > 0){
			$inv->addItem(new Snowball(0, $cnt));
		}
		if($data->getKills() <= 500){
			$inv->addItem(new Snowball(0, 8));
		}
		if($send){
			$inv->sendHeldItem($inv->getViewers());
			$inv->sendArmorContents($inv->getViewers());
		}
	}
	public function updateToMysql(MysqlConnection $con){
		if($this->updated){
			$con->query($this->insert ?
				"INSERT INTO kitpvp_kits(uid,kitid,name,helmet,chestplate,leggings,boots,weapon,food,arrows)VALUES(%d,%d,%s,%d,%d,%d,%d,%d,%d,%d);" :
				"UPDATE kitpvp_kits SET name=%3\$s,helmet=%4\$d,chestplate=%5\$d,leggings=%6\$d,boots=%7\$d,weapon=%8\$d,food=%9\$d,arrows=%10\$d WHERE uid=%1\$d AND kitid=%2\$d;", MysqlConnection::RAW,
				$this->uid, $this->kitid, $this->name, $this->helmet, $this->chestplate, $this->leggings, $this->boots, $this->weapon, $this->food, $this->arrows);
			$this->insert = false;
		}
	}
}
