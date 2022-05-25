<?php

namespace steellgold\quests\forms;

use dktapps\pmforms\FormIcon;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\player\Player;
use steellgold\quests\Quests;

class QuestsForms {

	public static function getQuestsForm(): MenuForm {
		return new MenuForm(
			Quests::getInstance()->getConfig()->get("forms")["quests"]["title"],
			Quests::getInstance()->getConfig()->get("forms")["quests"]["description"],
			[
				new MenuOption(Quests::getInstance()->getConfig()->get("forms")["button_quests"], null),
				new MenuOption(Quests::getInstance()->getConfig()->get("forms")["button_daily"], null),
				new MenuOption(Quests::getInstance()->getConfig()->get("forms")["button_consecutives_rewards"], null)
			],
			function (Player $player, int $selectedOption): void {
				switch ($selectedOption) {
					case 0:
						if (Quests::getInstance()->players[$player->getName()]["active_quest"] !== null) {
							$player->sendForm(self::getQuestForm(Quests::getInstance()->players[$player->getName()]));
							return;
						}

						$player->sendForm(self::getQuestsListForm());
						break;
					case 1:
						if (in_array(Quests::getInstance()->dailyQuest["id"], Quests::getInstance()->players[$player->getName()]["validated"])) {
							$player->sendMessage("§cVous avez déjà validé cette quête");
							return;
						}

						$player->sendForm(self::getQuestForm(Quests::getInstance()->dailyQuest, true));
						break;
					case 2:
						$player->sendForm(self::getConsecutiveForm());
						break;
				}
			}
		);
	}

	public static function getQuestsListForm(): MenuForm {
		$quests = [];
		$q = [];
		$i = 0;
		foreach (Quests::getInstance()->quests["default"] as $quest) {
			$quests[] = new MenuOption($quest["name"], new FormIcon($quest["icon"], str_starts_with($quest["icon"], "https://") ? FormIcon::IMAGE_TYPE_URL : FormIcon::IMAGE_TYPE_PATH));
			$q[$i] = $quest;
			$i++;
		}

		return new MenuForm(
			Quests::getInstance()->getConfig()->get("forms")["quests"]["title"],
			Quests::getInstance()->getConfig()->get("forms")["quests"]["description"],
			$quests,
			function (Player $player, int $selectedOption) use ($q): void {
				if(Quests::getInstance()->players[$player->getName()]["active_quest"] !== null) {
					$player->sendMessage(Quests::getInstance()->getConfig()->get("messages")["active_quest"]);
					return;
				}

				$player->sendForm(self::getQuestForm($q[$selectedOption], false,$player));
			}
		);
	}

	public static function getQuestForm(array $quest = [], bool $daily = false, Player $player = null): MenuForm {
		$tc = Quests::getInstance()->getConfig()->get("timecode");
		$time = ($quest["time"] !== null ?($quest["time"]["d"] . $tc["d"]. $quest["time"]["h"] . $tc["h"] . $quest["time"]["m"] . $tc["m"] . $quest["time"]["s"] . $tc["s"]) : Quests::getInstance()->getConfig()->get("forms")["no_time"]);

		$buttons = [
			new MenuOption(Quests::getInstance()->getConfig()->get("forms")["button_start"], null),
			new MenuOption(Quests::getInstance()->getConfig()->get("forms")["button_validate"], null),
		];

		return new MenuForm(
			str_replace("{NAME}", $quest["name"], Quests::getInstance()->getConfig()->get("forms")["quest"]["title"]),
			str_replace([
				"{DESCRIPTION}", "{REWARD}", "{TIME}"
			], [
				$quest["description"],
				$quest["rewards"][0][0]["name"] ?? $quest["rewards"]["name"],
				$daily ? (24 - date("H")) . $tc["h"] . (60 - date("i")) . $tc["m"] : ($time ?? "Aucun")
			], Quests::getInstance()->getConfig()->get("forms")["quest"]["description"]),
			$buttons,
			function (Player $player, int $selectedOption) use ($quest, $daily): void {
				if($selectedOption == 0){
					if(!$daily){
						if(Quests::getInstance()->players[$player->getName()]["active_quest"] !== null) {
							$player->sendMessage(Quests::getInstance()->getConfig()->get("messages")["active_quest"]);
							return;
						}

						Quests::getInstance()->players[$player->getName()]["active_quest"] = $quest["id"];
						Quests::getInstance()->players[$player->getName()]["started_at"] = time();
					}else{
						$player->sendMessage(Quests::getInstance()->getConfig()->get("messages")["quest_daily"]);
						return;
					}
					var_dump("quest started");
					var_dump(Quests::getInstance()->players[$player->getName()]);
				}else if($selectedOption == 1){
					if (Quests::getInstance()->players[$player->getName()]["quest_status"] == $quest["objective"]["count"]) {
						Quests::getInstance()->players[$player->getName()]["quest_status"] = 0;
						Quests::getInstance()->players[$player->getName()]["validated"][] = $quest["id"];
						Quests::getInstance()->players[$player->getName()]["consecutives"]++;
						$player->sendMessage(str_replace("{QUEST}", $quest["name"], Quests::getInstance()->getConfig()->get("messages")["quest_valited"]));
					} else {
						$player->sendMessage(str_replace(["{QUEST}", "{REMAINING_TIME}"], [$quest["rewards"][0][0]["name"] ?? $quest["rewards"]["name"], $quest["objective"]["count"] - Quests::getInstance()->players[$player->getName()]["quest_status"]], Quests::getInstance()->getConfig()->get("messages")["quest_unvalidated"]));
					}
				}else{
					var_dump("wtf ?");
				}
			}
		);
	}

	public static function getConsecutiveForm(): MenuForm {
		$rewards = [];
		$i = 0;
		$days = [];
		foreach (Quests::getInstance()->getConfig()->get("consecutive_day_rewards") as $day => $reward) {
			$rewards[] = new MenuOption($reward["name"], null);
			$days[$i] = $day;
			$i++;
		}

		return new MenuForm(
			Quests::getInstance()->getConfig()->get("forms")["consecutive_rewards"]["title"],
			Quests::getInstance()->getConfig()->get("forms")["consecutive_rewards"]["description"],
			$rewards,
			function (Player $player, int $selectedOption) use ($rewards, $days): void {
				if (Quests::getInstance()->players[$player->getName()]["consecutives"] < $days[$selectedOption]) {
					$player->sendMessage(str_replace("{REMAINING_DAYS}", ($days[$selectedOption] - Quests::getInstance()->players[$player->getName()]["consecutives"]), Quests::getInstance()->getConfig()->get("messages")["consecutive_not_reached"]));
					return;
				}

				if (!key_exists($days[$selectedOption], Quests::getInstance()->players[$player->getName()]["consecutives_claimed"])) {
					Quests::getInstance()->players[$player->getName()]["consecutives_claimed"][$days[$selectedOption]] = true;
					$player->sendMessage(str_replace("{DAY}", $days[$selectedOption], Quests::getInstance()->getConfig()->get("messages")["consecutive_claimed"]));
				} else {
					$player->sendMessage(Quests::getInstance()->getConfig()->get("messages")["consecutive_already_claimed"]);
				}
			}
		);
	}
}