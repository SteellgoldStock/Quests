<?php

namespace steellgold\quests;

use dktapps\pmforms\FormIcon;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use steellgold\quests\commands\QuestsCommand;
use steellgold\quests\listeners\EventListener;
use steellgold\quests\task\UpdateQuestTask;

class Quests extends PluginBase{

	public static $instance;

	/** @var array */
	public array $quests = [];
	/** @var array */
	public array $dailyQuest = [];
	/** @var array */
	public array $players = [];

	protected function onEnable(): void {
		self::$instance = $this;

		if(!file_exists($this->getDataFolder() . "config.yml")){
			$this->saveResource("config.yml");
			$this->getLogger()->info("Fichier de configuration: §aOK");
		}

		date_default_timezone_set($this->getConfig()->get("timezone"));

		$this->quests["daily"] = [];
		$this->quests["default"] = [];

		$players_file = new Config($this->getDataFolder() . "players.json", Config::JSON);
		$temporary_data = new Config($this->getDataFolder() . "temporary_data.json", Config::JSON);

		foreach ($this->getConfig()->get("quests") as $questID => $questData) {
			$this->quests[$questData["quest_type"]][$questID] = [
				"id" => $questID,
				"name" => $questData["name"],
				"description" => $questData["description"],
				"icon" => $questData["icon"],
				"rewards" => $this->generateRewardFromArray($questData["rewards"]),
				"quest_action_type" => $questData["quest_action_type"],
				"time" => $questData["time"] ?? null,
				"objective" => $questData["objective"],
			];
		}

		foreach ($players_file->getAll() as $player => $playerData) {
			$this->players[$player] = [
				"active_quest" => $playerData["active_quest"],
				"started_at" => 0,
				"quest_status" => $playerData["quest_status"],
				"validated" => $playerData["validated"],
				"consecutives" => $playerData["consecutives"],
				"consecutives_claimed" => $playerData["consecutives_claimed"],
			];
		}

		if($temporary_data->get("reset_date") !== false){
			if(date("m.d.y") !== $temporary_data->get("reset_date")){
				$this->dailyQuest = $temporary_data->get("daily_quest");
			}else{
				$this->dailyQuest = [];
			}
			$this->getLogger()->notice("La quête reste la même, il reste " . (24 - date("H")) . " heures avant la prochaine quête");
		}else{
			$temporary_data->set("reset_date", date("m.d.y", strtotime("+1 day")));
			$temporary_data->save();

			$quest = Quests::getInstance()->quests["daily"][array_rand(Quests::getInstance()->quests["daily"])];
			$quest["rewards"][0] = $this->generateRewardFromArray($quest["rewards"]);
			$this->dailyQuest = $quest;

			$this->getLogger()->notice("Aucune quête n'a été trouvée, une nouvelle quête a été générée");
		}

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
		$this->getServer()->getCommandMap()->register("quests", new QuestsCommand($this,"quests","Voir les quêtes disponibles"));
		$this->getScheduler()->scheduleRepeatingTask(new UpdateQuestTask(), 20 * 120);
	}

	protected function onDisable(): void {
		$players_file = new Config($this->getDataFolder() . "players.json", Config::JSON);
		$temporary_data = new Config($this->getDataFolder() . "temporary_data.json", Config::JSON);
		foreach ($this->players as $player => $data) {
			$players_file->set($player,$data);
			$players_file->save();
		}

		if(date("m.d.y") !== $temporary_data->get("reset_date")){
			$temporary_data->set("daily_quest",$this->dailyQuest);
			$temporary_data->save();
			$this->getLogger()->alert("La quête journalière a été sauvegardé!");
		}
	}

	/**
	 * @return mixed
	 */
	public static function getInstance(): Quests {
		return self::$instance;
	}

	public function generateRewardFromArray(array $rewards) : array {
		if(count($rewards) >= 2){
			return $rewards[array_rand($rewards)];
		}
		return $rewards;
	}

	public function calculTime(int $int, bool $text): string|array {
		$day = floor($int / 86400);
		$hourSec = $int % 86400;
		$hour = floor($hourSec / 3600);
		$minuteSec = $hourSec % 3600;
		$minute = floor($minuteSec / 60);
		$remainingSec = $minuteSec % 60;
		$second = ceil($remainingSec);
		if(!isset($day)) $day = 0;
		if (!isset($hour)) $hour = 0;
		if (!isset($minute)) $minute = 0;
		if (!isset($second)) $second = 0;

		$t = $this->getConfig()->get("timecode");
		if($text) return $day . $t["d"] . $hour . $t["h"] . $minute . $t["m"] . $second . $t["s"];
		else return ["d" => $day, "h" => $hour, "m" => $minute, "s" => $second];
	}

	public function arrayToTime(int $day, int $hour, int $minute, int $second): int {
		$second += (($day * 24 + $hour) * 60 + $minute) * 60;
		return $second;
	}
}