<?php

namespace steellgold\quests\listeners;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\world\Position;
use steellgold\quests\instances\Quest;
use steellgold\quests\Quests;

class PlayerListeners implements Listener {

	public static array $blocks = [];

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		if (!array_key_exists($player->getName(), Quests::getInstance()->players)) {
			Quests::getInstance()->players[$player->getName()] = ["quests" => [], "consecutives" => 0, "consecutives_claimed" => ["rewards" => [], "quests" => []], "daily_quests" => [],"last_claimed" => null];
		}
	}

	public function onKill(PlayerDeathEvent $event) {
		$cause = $event->getEntity()->getLastDamageCause();
		if ($cause instanceof EntityDamageByEntityEvent) {
			$killer = $cause->getDamager();
			if ($killer instanceof Player) {
				$quest = self::getProgressQuest($killer);

				if ($quest->type == "kill") {
					$quest->addProgress($killer, 1, $quest->isDaily());
				}
			}
		}
	}

	public function onCraft(CraftItemEvent $event) {
		$player = $event->getPlayer();

		$quest = self::getProgressQuest($player);

		if ($quest->type == "craft") {
			if ($quest->getObjective()->getItem() == $event->getOutputs()[0]) {
				$quest->addProgress($player, 1, $quest->isDaily());
			}
		}
	}

	public function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$quest = self::getProgressQuest($player);
		if($quest == null) return;

		self::blockExists($block->getPosition());
		if ($quest->getType() == "place") {
			$this->acceptProgress($block, $quest, $player, true);
		}
	}

	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$quest = self::getProgressQuest($player);
		if($quest == null) return;

		if ($quest->getType() == "break") {
			$this->acceptProgress($block, $quest, $player);
		}
	}

	public static function isNatural(Position $position): bool {
		$position = $position->getX() . "," . $position->getY() . "," . $position->getZ() . "," . $position->getWorld()->getFolderName();
		if (key_exists($position, self::$blocks)) {
			return false;
		}

		return true;
	}

	public static function blockExists(Position $position): bool {
		$position = $position->getX() . "," . $position->getY() . "," . $position->getZ() . "," . $position->getWorld()->getFolderName();
		if (key_exists($position, self::$blocks)) {
			return true;
		}

		self::$blocks[$position] = true;
		return false;
	}

	public static function getProgressQuest(Player $player): ?Quest {
		if (isset(Quests::getInstance()->players[$player->getName()]["daily_quests"][Quests::getInstance()->daily->id]) and Quests::getInstance()->players[$player->getName()]["daily_quests"][Quests::getInstance()->daily->id]["phase"] == "progress") {
			return Quest::getFromID(Quests::getInstance()->daily->id);
		}

		foreach (Quests::getInstance()->players[$player->getName()]["quests"] as $questID => $questData) {
			if (Quests::getInstance()->players[$player->getName()]["quests"][$questID]["phase"] == "progress") {
				return Quest::getFromID($questID);
			}
		}

		return null;
	}

	/**
	 * @param Block $block
	 * @param Quest|null $quest
	 * @param Player $player
	 * @param bool $place
	 * @return void
	 */
	public function acceptProgress(Block $block, ?Quest $quest, Player $player, bool $place = false): void {
		if ($block->getId() == $quest->getObjective()->getBlock()->getId()) {
			if ($place) $quest->addProgress($player, 1, $quest->isDaily());
			else if(self::isNatural($block->getPosition())) $quest->addProgress($player, 1, $quest->isDaily());
		}
	}
}