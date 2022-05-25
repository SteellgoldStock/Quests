<?php

namespace steellgold\quests\listeners;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use steellgold\quests\forms\QuestsForms;
use steellgold\quests\Quests;

class EventListener implements Listener {

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		if (!isset(Quests::getInstance()->players[$player->getName()])) {
			Quests::getInstance()->players[$player->getName()] = [
				"active_quest" => null,
				"started_at" => 0,
				"quest_status" => 0,
				"validated" => [],
				"consecutives" => 0,
				"consecutives_claimed" => [],
			];
		}
	}
}