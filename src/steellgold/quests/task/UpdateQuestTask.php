<?php

namespace steellgold\quests\task;

use JsonException;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Config;
use steellgold\quests\instances\Quest;
use steellgold\quests\listeners\PlayerListeners;
use steellgold\quests\Quests;

class UpdateQuestTask extends Task {

	/**
	 * @throws JsonException
	 */
	public function onRun(): void {
		PlayerListeners::$blocks = [];
		$data = new Config(Quests::getInstance()->getDataFolder() . "data.yml", Config::YAML);
		self::checkConsecutives();

		if ($data->get("next") == date("d/m/y")) {
			self::generate();

			$data->set("next", date("d/m/y", strtotime("+1 day")));
			$data->save();
		}
	}

	public static function checkConsecutives(bool $force = false){
		$data = new Config(Quests::getInstance()->getDataFolder() . "data.yml", Config::YAML);

		if ($force OR date("d/m/y") == $data->get("next")) {
			if($data->exists("daily_quest")){
				Quests::getInstance()->daily = Quest::fromStdClass(json_decode($data->get("daily_quest")),json_decode($data->get("icon")),json_decode($data->get("objective")),json_decode($data->get("reward")));

				foreach (Quests::getInstance()->players as $player => $playerData) {
					if(!in_array(Quests::getInstance()->daily->id, $playerData["consecutives_claimed"]["quests"])){
						if(Quests::getInstance()->getConfig()->get("log",true)) Server::getInstance()->getLogger()->info("[LOG] §a" . $player . "§f n'a pas réalisé la quête quotidienne du §a" . date("d/m/y", strtotime("-1 day")) . " §fson score consécutif étant à §a".Quests::getInstance()->players[$player]["consecutives"]." §fa été réinitialisé");
						Quests::getInstance()->players[$player]["consecutives"] = 0;
						Quests::getInstance()->players[$player]["consecutives_claimed"]["quests"] = [];
					}else{
						if(Quests::getInstance()->getConfig()->get("log",true)) Server::getInstance()->getLogger()->info("[LOG] §a" . $player . "§f a réalisé la quête quotidienne du §a" . date("d/m/y", strtotime("-1 day")) . " §fson score consécutif étant à §a".Quests::getInstance()->players[$player]["consecutives"]." §fa été augmenté de §a1 point §f!");
					}
				}

				Quests::getInstance()->daily = null;
			}
		}
	}

	/**
	 * @throws JsonException
	 */
	public static function generate(bool $force = false): void {
		$data = new Config(Quests::getInstance()->getDataFolder() . "data.yml", Config::YAML);
		if (date("d/m/y") == $data->get("next") OR $force) {
			self::checkConsecutives($force);

			$line = [];
			foreach (Quests::getInstance()->getQuests() as $quest) {
				if ($quest->isDaily()) {
					$line[] = $quest;
				}
			}

			if($line == []){
				Server::getInstance()->getLogger()->error("Aucunes quêtes quotidiennes n'ont été trouvées, veuillez créer au moins une quête quotidienne avant de lancer le serveur");
				Quests::getInstance()->getServer()->shutdown();
				return;
			}

			$questChoosed = $line[array_rand($line)];
			if (count($questChoosed->getReward()) == 1) {
				$reward = $questChoosed->getReward()[0];
			} else {
				$rewards = [];
				foreach ($questChoosed->getReward() as $reward) {
					$rewards[] = $reward;
				}

				$reward = $rewards[array_rand($rewards)];
			}

			$date = Quests::dateToFrench("now", "l j F Y");
			Server::getInstance()->broadcastMessage(str_replace(["{DATE}", "{QUEST_NAME}", "{REWARD_NAME}"], [
				$date,
				$questChoosed->getName(),
				$reward->getName()
			], Quests::getInstance()->getConfig()->get("messages")["daily_quest"]["choosed"]
			));

			Quests::getInstance()->recent_reset_date = date("d/m/Y");
			Quests::getInstance()->daily = $questChoosed;
			Quests::getInstance()->daily->setActiveReward($reward);
		}
	}
}