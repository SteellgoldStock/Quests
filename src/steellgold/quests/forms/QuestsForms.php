<?php

namespace steellgold\quests\forms;

use pocketmine\item\ItemFactory;
use pocketmine\player\Player;
use steellgold\dktapps\pmforms\MenuForm;
use steellgold\dktapps\pmforms\MenuOption;
use steellgold\dktapps\pmforms\ModalForm;
use steellgold\quests\instances\Quest;
use steellgold\quests\instances\Reward;
use steellgold\quests\Quests;

class QuestsForms {

	public static function getHomeForm(Player $player): MenuForm {
		$daily = [
			"h" => date("H", strtotime("tomorrow") - time()) - 1,
			"m" => date("i", strtotime("tomorrow") - time()),
			"s" => date("s", strtotime("tomorrow") - time()),
		];

		$options = [];
		$fail = 0;
		foreach (Quests::getInstance()->players[$player->getName()]["quests"] as $questID => $questData) {
			if (Quests::getInstance()->players[$player->getName()]["quests"][$questID]["phase"] == "progress") {
				$quest = Quest::getFromID($questID);

				$sub = "";
				if($quest->getObjective()->getAmount() == Quests::getInstance()->players[$player->getName()]["quests"][$questID]["progress"]){
					$sub = Quests::getInstance()->getConfig()->get("forms")["menu"]["reward"];
				}

				if ($quest->time !== null) {
					$endTime = Quests::calculTime(Quests::getInstance()->players[$player->getName()]["quests"][$questID]["end_time"] - time());

					if($quest->getObjective()->getAmount() !== Quests::getInstance()->players[$player->getName()]["quests"][$questID]["progress"]){
						if(time() > Quests::getInstance()->players[$player->getName()]["quests"][$questID]["end_time"]){
							$sub = Quests::getInstance()->getConfig()->get("forms")["menu"]["timeout"];
						}else{
							$sub = str_replace([
								"{days}",
								"{hours}",
								"{minutes}",
								"{seconds}"
							], [
								$endTime["d"],
								$endTime["h"],
								$endTime["m"],
								$endTime["s"]
							], Quests::getInstance()->getConfig()->get("forms")["menu"]["time"]);
						}
					}else{
						$sub = Quests::getInstance()->getConfig()->get("forms")["menu"]["reward"];
					}

					$options[] = new MenuOption(str_replace("{QUEST_NAME}",$quest->getName(),Quests::getInstance()->getConfig()->get("forms")["menu"]["quests_quest"])."\n".$sub,$quest->getIcon());
				}else $options[] = new MenuOption(str_replace("{QUEST_NAME}",$quest->getName(),Quests::getInstance()->getConfig()->get("forms")["menu"]["quests_quest"])."\n".$sub,$quest->getIcon());
			}else $fail++;
		}

		if ($fail == count(Quests::getInstance()->players[$player->getName()]["quests"])) {
			$options[] = new MenuOption(Quests::getInstance()->getConfig()->get("forms")["menu"]["quests"]);
		}

		$options[] = new MenuOption(str_replace(["{h}","{m}","{s}"],[$daily["h"],$daily["m"],$daily["s"]],Quests::getInstance()->getConfig()->get("forms")["menu"]["daily"]));
		$options[] = new MenuOption("Récompenses des quêtes consécutives");

		return new MenuForm(
			Quests::getInstance()->getConfig()->get("forms")["menu"]["title"],
			Quests::getInstance()->getConfig()->get("forms")["menu"]["text"],
			$options,
			function (Player $player, int $selectedOption): void {
				if ($selectedOption == 0) {
					$fail = 0;

					foreach (Quests::getInstance()->players[$player->getName()]["quests"] as $questID => $questData) {
						if (Quests::getInstance()->players[$player->getName()]["quests"][$questID]["phase"] == "progress") {
							$quest = Quest::getFromID($questID);
							if(isset(Quests::getInstance()->players[$player->getName()]["quests"][$questID]["end_time"])){
								if(time() > Quests::getInstance()->players[$player->getName()]["quests"][$questID]["end_time"]){
									if($quest->getObjective()->getAmount() == Quests::getInstance()->players[$player->getName()]["quests"][$questID]["progress"]){
										$player->sendTip(Quests::getInstance()->getConfig()->get("messages")["quest_already_progress"]);
										$player->sendForm(self::getQuestProgressForm($player, Quest::getFromID($questID)));
										return;
									}

									$player->sendMessage(Quests::getPrefix(true) . str_replace("{QUEST_NAME}", $quest->getName(), Quests::getInstance()->getConfig()->get("messages")["timeout"]));
									$quest->cancelProgress($player,true);
									return;
								}
							}

							$player->sendTip(Quests::getInstance()->getConfig()->get("messages")["quest_already_progress"]);
							$player->sendForm(self::getQuestProgressForm($player, Quest::getFromID($questID)));
						} else $fail++;
					}

					if ($fail == count(Quests::getInstance()->players[$player->getName()]["quests"])) {
						if(QuestsForms::getQuestsForm($player) == null){
							return;
						}

						$player->sendForm(QuestsForms::getQuestsForm($player));
					}
				} elseif ($selectedOption == 1) {
					if (!key_exists(Quests::getInstance()->daily->id, Quests::getInstance()->players[$player->getName()]["daily_quests"])) {
						if (Quests::getInstance()->players[$player->getName()]["last_claimed"] == Quests::getInstance()->recent_reset_date) {
							$player->sendMessage(Quests::getPrefix() . str_replace("{DATE}", Quests::getInstance()->recent_reset_date, Quests::getInstance()->getConfig()->get("messages")["daily_quest"]["daily_quest_already_done"]));
						} else {
							Quests::getInstance()->daily->startProgress($player);
							$player->sendForm(QuestsForms::getQuestProgressForm($player, Quests::getInstance()->daily));
						}
					} else {
						$player->sendForm(QuestsForms::getQuestProgressForm($player, Quests::getInstance()->daily));
					}
				} elseif ($selectedOption == 2) {
					$player->sendForm(QuestsForms::getConsecutiveForm($player));
				}
			}
		);
	}

	public static function getQuestsForm(Player $player): ?MenuForm {
		$options = [];
		$quests = [];
		foreach (Quests::getInstance()->getQuests() as $quest) {
			if (!$quest->isDaily()) {
				if (!$quest->isCompleted($player)) {
					if (!$quest->isCancelled($player)) {
						if (!$quest->isTimeOut($player)) {
							$options[] = new MenuOption($quest->getName(), $quest->getIcon());
							$quests[] = $quest;
						}
					}
				}
			}
		}

		if($options == null){
			$player->sendMessage(Quests::getPrefix(true) . Quests::getInstance()->getConfig()->get("messages")["quest_completed_all"]);
			return null;
		}

		return new MenuForm(
			Quests::getInstance()->getConfig()->get("forms")["quests"]["title"],
			Quests::getInstance()->getConfig()->get("forms")["quests"]["text"],
			$options,
			function (Player $player, int $selectedOption) use ($quests): void {
				$player->sendForm(QuestsForms::getQuestForm($player, $quests[$selectedOption]));
			}
		);
	}

	public static function getQuestForm(Player $player, Quest $quest): MenuForm {
		return new MenuForm(
			$quest->name,
			$quest->formatedDescription($player), [
			new MenuOption(Quests::getInstance()->getConfig()->get("forms")["accept"]),
		],
			function (Player $player, int $selectedOption) use ($quest): void {
				if ($selectedOption == 0) {
					$quest->startProgress($player);
					$player->sendMessage(Quests::getPrefix() . str_replace("{QUEST_NAME}", $quest->name, Quests::getInstance()->getConfig()->get("messages")["quest_accepted"]));
				}
			},
		);
	}

	public static function getQuestProgressForm(Player $player, Quest $quest): MenuForm {
		return new MenuForm(
			$quest->name,
			$quest->formatedDescription($player, true), [
			new MenuOption(Quests::getInstance()->getConfig()->get("forms")["validate"]),
			new MenuOption(Quests::getInstance()->getConfig()->get("forms")["cancel"]),
		],
			function (Player $player, int $selectedOption) use ($quest): void {
				if ($selectedOption == 0) {
					if ($quest->getProgress($player, $quest->isDaily()) == $quest->getObjective()->getAmount()) {
						$player->sendMessage(Quests::getPrefix() . str_replace("{QUEST_NAME}", $quest->name, Quests::getInstance()->getConfig()->get("messages")["quest_completed"]));
						$quest->completeQuest($player);
					} elseif ($quest->type == "bring") {
						$toDel = $quest->getObjective()->getAmount() - $quest->getProgress($player, $quest->isDaily());
						$item = $quest->getObjective()->getItem();

						if ($player->getInventory()->contains(ItemFactory::getInstance()->get($item->getId(), $item->getMeta(), $toDel))) {
							$player->getInventory()->removeItem(ItemFactory::getInstance()->get($item->getId(), $item->getMeta(), $toDel));
							$quest->addProgress($player, $toDel, $quest->isDaily());
						} else {
							$player->sendMessage(Quests::getPrefix(true) . str_replace(["{COUNT}", "{NAME}"], [$toDel, $item->getName()], Quests::getInstance()->getConfig()->get("messages")["item_missing"]));
						}
					} else {
						$name = "Missing Name";
						if ($quest->getObjective()->getItem() !== null) {
							$name = $quest->getObjective()->getItem()->getName();
						} elseif ($quest->getObjective()->getBlock() !== null) {
							$name = $quest->getObjective()->getBlock()->getName();
						}

						$player->sendMessage(Quests::getPrefix(true) . str_replace([
								"{TYPE}",
								"{COUNT}",
								"{NAME}",
								"{QUEST_NAME}",
							], [
								$quest->getType(true),
								($quest->getObjective()->getAmount() - $quest->getProgress($player, $quest->isDaily())),
								$name,
								$quest->getName(),
							],
								Quests::getInstance()->getConfig()->get("messages")["quest_missing"]
							)
						);
					}
				} elseif ($selectedOption == 1) {
					if ($quest->isDaily()) {
						$player->sendMessage(Quests::getPrefix(true) . Quests::getInstance()->getConfig()->get("messages")["daily_quest"]["cannot_cancel_daily"]);
						return;
					}

					$player->sendForm(self::cancelQuest($quest));
				}
			},
		);
	}

	public static function cancelQuest(Quest $quest): ModalForm {
		return new ModalForm(
			Quests::getInstance()->getConfig()->get("forms")["quest_cancel"]["title"],
			str_replace("{QUEST_NAME}", $quest->name, Quests::getInstance()->getConfig()->get("forms")["quest_cancel"]["text"]),
			function (Player $player, bool $choice) use ($quest): void {
				if ($choice) {
					$quest->cancelProgress($player);
					$player->sendMessage(Quests::getPrefix() . str_replace("{QUEST_NAME}", $quest->name, Quests::getInstance()->getConfig()->get("messages")["quest_canceled"]));
				}
			},
			Quests::getInstance()->getConfig()->get("forms")["quest_cancel"]["yes"],
			Quests::getInstance()->getConfig()->get("forms")["quest_cancel"]["no"]
		);
	}

	public static function getConsecutiveForm(Player $player): MenuForm {
		$rewards = [];
		$list = [];
		foreach (Quests::getInstance()->rewards as $day => $reward) {
			$rewards[] = new MenuOption($reward->getName(), null);
			$list[] = $reward;
		}

		return new MenuForm(
			"Récompenses de quêtes consécutives",
			str_replace("{DAYS_COUNT}",Quests::getInstance()->players[$player->getName()]["consecutives"] ?? 0,Quests::getInstance()->getConfig()->get("forms")["consecutive"]["text"]),
			$rewards,
			function (Player $player, int $selectedOption) use ($list): void {
				/** @var Reward $reward */
				$reward = $list[$selectedOption];
				if(in_array($reward->getDay(),Quests::getInstance()->players[$player->getName()]["consecutives_claimed"]["rewards"])){
					$player->sendMessage(Quests::getPrefix(true) . str_replace(["{REWARD_NAME}","{DAYS_COUNT}"],[$reward->getName(),Quests::getInstance()->players[$player->getName()]["consecutives"]],Quests::getInstance()->getConfig()->get("messages")["consecutive_reward_already_received"]));
					return;
				}

				if($list[$selectedOption]->getDay() > Quests::getInstance()->players[$player->getName()]["consecutives"]){
					$player->sendMessage(Quests::getPrefix(true) . str_replace(["{DAYS}","{REWARD_NAME}","{DAYS_COUNT}"],[$reward->getDay() - Quests::getInstance()->players[$player->getName()]["consecutives"], $reward->getName(),Quests::getInstance()->players[$player->getName()]["consecutives"]],Quests::getInstance()->getConfig()->get("messages")["consecutive_reward_day_missing"]));
					return;
				}

				$reward->giveReward($player);
				$player->sendMessage(Quests::getPrefix() . str_replace(["{REWARD_NAME}","{DAYS}","{DAYS_COUNT}"],[$reward->getName(), $reward->getDay(),Quests::getInstance()->players[$player->getName()]["consecutives"]],Quests::getInstance()->getConfig()->get("messages")["consecutive_reward_received"]));
				Quests::getInstance()->players[$player->getName()]["consecutives_claimed"]["rewards"][] = $reward->getDay();
			}
		);
	}
}