<?php

namespace steellgold\quests\task;

use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use steellgold\quests\Quests;

class UpdateQuestTask extends Task {
	public function onRun(): void {
		$temporary_data = new Config(Quests::getInstance()->getDataFolder() . "temporary_data.json", Config::JSON);

		if(date("m.d.y") == $temporary_data->get("reset_date")){
			Quests::getInstance()->getLogger()->notice("Une nouvelle quête à été choisi !");
			Quests::getInstance()->dailyQuest = Quests::getInstance()->quests["daily"][array_rand(Quests::getInstance()->quests["daily"])];
			$temporary_data->set("reset_date", date("m.d.y", strtotime("+1 day")));
			$temporary_data->save();
		}
	}
}