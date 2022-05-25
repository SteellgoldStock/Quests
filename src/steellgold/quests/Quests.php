<?php

namespace steellgold\quests;

use JsonException;
use pocketmine\block\BlockFactory;
use pocketmine\item\ItemFactory;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use steellgold\dktapps\pmforms\FormIcon;
use steellgold\quests\commands\QuestsCommand;
use steellgold\quests\instances\Objective;
use steellgold\quests\instances\Quest;
use steellgold\quests\instances\Reward;
use steellgold\quests\listeners\PlayerListeners;
use steellgold\quests\task\UpdateQuestTask;

class Quests extends PluginBase {

	public static mixed $instance;

	/** @var array */
	public array $players = [];

	/** @var Quest[] */
	public array $quests = [];

	/** @var Reward[] */
	public array $rewards = [];

	/** @var ?Quest */
	public ?Quest $daily;

	public string $prefix = "";

	public string $recent_reset_date = "";

	public static function getPrefix(bool $error = false) : string {
		return $error ? self::getInstance()->getConfig()->get("messages")["prefix_error"] : self::getInstance()->getConfig()->get("messages")["prefix"];
	}

	protected function onLoad(): void {
		date_default_timezone_set("Europe/Paris");
	}

	/**
	 * @throws JsonException
	 */
	protected function onEnable(): void {
		self::$instance = $this;

		if (!file_exists($this->getDataFolder() . "config.yml")) {
			$this->saveResource("config.yml");
			$this->saveResource("quests.yml");
			$this->getLogger()->info("Fichier de configuration: §aOK");
		}

		$data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
		$this->recent_reset_date = $data->get("recent_reset_date",date("d/m/Y"));

		$players = new Config($this->getDataFolder() . "players.yml", Config::YAML);
		$this->prefix = $this->getConfig()->get("messages")["prefix"];

		foreach ($players->getAll() as $player => $pdata) {
			$this->players[$player] = $pdata;
		}

		foreach ($this->getConfig()->get("consecutive_day_rewards") as $day => $rewardData) {
			$item = null;
			$count = 0;
			if (isset($rewardData["item"])) {
				$itemArray = explode(":", $rewardData["item"]);
				$item = ItemFactory::getInstance()->get($itemArray[0], $itemArray[1], $itemArray[2]);
				$count = $itemArray[2];
			}
			$this->rewards[$day] = new Reward($rewardData["name"],$item,$rewardData["command"] ?? null,$count,$day);
		}

		$quests = new Config($this->getDataFolder() . "quests.yml", Config::YAML);
		foreach ($quests->get("quests") as $quest) {
			$rewards = [];
			foreach ($quest["rewards"] as $reward) {
				$item = null;
				if (isset($reward["item"])) {
					$itemArray = explode(":", $reward["item"]);
					$item = ItemFactory::getInstance()->get($itemArray[0], $itemArray[1], $reward["amount"]);
				}

				$rewards[] = new Reward($reward["name"], $item, $reward["command"] ?? null, $reward["amount"] ?? 0);
			}

			$objective = new Objective();
			if (isset($quest["objective"]["block"])) {
				$blockArray = explode(":", $quest["objective"]["block"]);
				$objective->setBlock(BlockFactory::getInstance()->get($blockArray[0], $blockArray[1]));
			}elseif (isset($quest["objective"]["item"])) {
				$itemArray = explode(":", $quest["objective"]["item"]);
				$objective->setItem(ItemFactory::getInstance()->get($itemArray[0], $itemArray[1]));
			}
			$objective->setAmount($quest["objective"]["amount"]);

			if(isset($quest["id"])){
				$this->quests[] = new Quest(
					$quest["name"],
					$quest["description"],
					$quest["id"],
					isset($quest["icon"]) ? new FormIcon($quest["icon"]["path"], $quest["icon"]["type"] == "path" ? FormIcon::IMAGE_TYPE_PATH : FormIcon::IMAGE_TYPE_URL) : null,
					$rewards,
					(boolean)$quest["daily"],
					$quest["type"] ?? "default",
					$objective,
					null,
					$quest["time"] ?? null
				);

				$this->getLogger()->info("§aQuête: §f" . $quest["name"] . "§a sauvegardé avec succès");
			}else{
				$this->getLogger()->warning("Quête sans identifiant: " . $quest["name"] . " elle n'a pas été chargée");
			}
		}

		if (!$data->exists("next")) {
			$data->set("next", date('d/m/y'));
			$data->save();
		}

		if (date("d/m/y") == $data->get("next")) {
			UpdateQuestTask::checkConsecutives();
			UpdateQuestTask::generate();
		} else {
			if($data->exists("daily_quest")){
				$this->daily = Quest::fromStdClass(json_decode($data->get("daily_quest")),json_decode($data->get("icon")),json_decode($data->get("objective")),json_decode($data->get("reward")));
			}else{
				var_dump("cc");
				UpdateQuestTask::generate(true);
			}
		}

		$this->getServer()->getCommandMap()->register("quests", new QuestsCommand("quests", "Voir les quêtes disponibles"));
		$this->getServer()->getPluginManager()->registerEvents(new PlayerListeners(), $this);
		$this->getScheduler()->scheduleRepeatingTask(new UpdateQuestTask(), 20 * 60);
	}

	/**
	 * @throws JsonException
	 */
	protected function onDisable(): void {
		$data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
		if (date("d/m/y") !== $data->get("next")) {
			if (isset($this->daily)) {
				$data->set("daily_quest", json_encode(Quests::getInstance()->daily));
				$data->set("icon", json_encode(Quests::getInstance()->daily->icon));
				$data->set("objective", json_encode(Quests::getInstance()->daily->objective));
				$data->set("reward", json_encode(Quests::getInstance()->daily->activeReward));
				$data->set("recent_reset_date", $this->recent_reset_date);
				$data->save();
				$this->getLogger()->alert("La quête journalière a été sauvegardé!");
			}
		}

		$players = new Config($this->getDataFolder() . "players.yml", Config::YAML);
		foreach ($this->players as $player => $data) {
			$players->set($player, $data);
			$players->save();
		}
	}

	/**
	 * @return mixed
	 */
	public static function getInstance(): Quests {
		return self::$instance;
	}

	/**
	 * @return Quest[]
	 */
	public function getQuests(): array {
		return $this->quests;
	}

	public static function dateToFrench($date, $format): array|string {
		$english_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
		$french_days = array('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche');
		$english_months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
		$french_months = array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre');
		return str_replace($english_months, $french_months, str_replace($english_days, $french_days, date($format, is_integer($date) ? $date : strtotime($date))));
	}

	public static function calculTime(int $int): array {
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

		return ["d" => $day, "h" => $hour, "m" => $minute, "s" => $second];
	}
}