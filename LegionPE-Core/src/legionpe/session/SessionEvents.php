<?php

namespace legionpe\session;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;

interface SessionEvents{
	public function h_onPreCmd(PlayerCommandPreprocessEvent $event);
	public function h_onDamage(EntityDamageEvent $event);
	public function h_onItemConsume(PlayerItemConsumeEvent $event);
	public function h_onDropItem(PlayerDropItemEvent $event);
	public function h_onInteract(PlayerInteractEvent $event);
	public function h_onRespawn(PlayerRespawnEvent $event);
	public function h_onMove(PlayerMoveEvent $event);
	public function h_onBreak(BlockBreakEvent $event);
	public function h_onPlace(BlockPlaceEvent $event);
	public function h_onOpenInv(InventoryOpenEvent $event);
	public function h_onPickup(InventoryPickupItemEvent $event);
	public function h_onChat(PlayerChatEvent $event);
	public function h_onItemHeld(PlayerItemHeldEvent $event);
	public function l_onBlockPlace(BlockPlaceEvent $event);
	public function l_onBlockBreak(BlockBreakEvent $event);
	public function l_onDamage(EntityDamageEvent $event);
	public function mon_onMove(PlayerMoveEvent $event);
	public function mon_onTeleport(EntityTeleportEvent $event);
	public function hst_onInteract(PlayerInteractEvent $event);
}
