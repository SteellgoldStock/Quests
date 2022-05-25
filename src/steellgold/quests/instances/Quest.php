<?php

namespace steellgold\quests\instances;

use pocketmine\player\Player;
use pocketmine\Server;
use steellgold\dktapps\pmforms\FormIcon;
use steellgold\quests\Quests;

class Quest {

	const PROGRESS = [
		"{DESCRIPTION}",
		"{REWARD_NAME}",
		"{DURATION}",
		"{PROGRESS}",
		"{TOTAL}",
		"{TIME_LEFT}"
	];

	const NO_PROGRESS = [
		"{DESCRIPTION}",
		"{REWARD_NAME}",
		"{DURATION}",
		"{TOTAL}"
	];

	/**
	 * @param string $name
	 * @param string $description
	 * @param int $id
	 * @param ?FormIcon $icon
	 * @param Reward[] $rewards
	 * @param bool $daily
	 * @param string $type
	 * @param Objective $objective
	 * @param Reward|null $activeReward
	 * @param ?array $time
	 */
	public function __construct(
		public string    $name,
		public string    $description,
		public int       $id,
		public ?FormIcon $icon,
		public array     $rewards,
		public bool      $daily,
		public string    $type,
		public Objective $objective,
		public ?Reward   $activeReward,
		public ?array    $time
	) {
	}

	public static function getFromID(int|string $questID): ?Quest {
		foreach (Quests::getInstance()->getQuests() as $quest) {
			if ($quest->getId() === $questID) {
				return $quest;
			}
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name ?? "Default Name";
	}

	/**
	 * @return Reward[]
	 */
	public function getReward(): array {
		return $this->rewards;
	}

	/**
	 * @return bool
	 */
	public function isDaily(): bool {
		return $this->daily;
	}

	/**
	 * @param Reward|null $activeReward
	 */
	public function setActiveReward(?Reward $activeReward): void {
		$this->activeReward = $activeReward;
	}

	/**
	 * @return FormIcon|null
	 */
	public function getIcon(): ?FormIcon {
		return $this->icon ?? null;
	}

	public static function fromStdClass(object $object, object $icon, object $objective, object $activeReward): Quest {
		Server::getInstance()->getLogger()->notice("Quest: " . $object->name . " re-loaded");
		return new Quest(
			$object->name,
			$object->description,
			$object->id,
			FormIcon::fromStdClass($icon),
			$object->rewards,
			$object->daily,
			$object->type,
			Objective::fromStdClass($objective),
			$object->activeReward ? Reward::fromStdClass($activeReward) : null,
			$object->time ?? null
		);
	}

	public function isCompleted(Player $player): bool {
		if (!key_exists($this->id,Quests::getInstance()->players[$player->getName()]["quests"])) return false;
		return Quests::getInstance()->players[$player->getName()]["quests"][$this->id]["phase"] == "completed";
	}

	public function isCancelled(Player $player): bool {
		if (!key_exists($this->id,Quests::getInstance()->players[$player->getName()]["quests"])) return false;
		return Quests::getInstance()->players[$player->getName()]["quests"][$this->id]["phase"] == "cancelled";
	}

	public function isTimeOut(Player $player): bool {
		if (!key_exists($this->id,Quests::getInstance()->players[$player->getName()]["quests"])) return false;
		return Quests::getInstance()->players[$player->getName()]["quests"][$this->id]["phase"] == "timeout";
	}

	public function completeQuest(Player $player): void {
		if ($this->isDaily()) {
			$this->activeReward->giveReward($player);

			Quests::getInstance()->players[$player->getName()]["daily_quests"][$this->id] = [
				"phase" => "completed",
				"started_at" => Quests::getInstance()->players[$player->getName()]["daily_quests"][$this->id]["started_at"],
				"completed_at" => time(),
				"progress" => Quests::getInstance()->players[$player->getName()]["daily_quests"][$this->id]["progress"] ?? $this->getObjective()->getAmount()
			];
			Quests::getInstance()->players[$player->getName()]["consecutives"]++;
			Quests::getInstance()->players[$player->getName()]["consecutives_claimed"]["quests"][] = $this->id;
			Quests::getInstance()->players[$player->getName()]["last_claimed"] = date("d/m/Y");
			unset(Quests::getInstance()->players[$player->getName()]["daily_quests"][$this->id]);
			return;
		}

		Quests::getInstance()->players[$player->getName()]["quests"][$this->id] = [
			"phase" => "completed",
			"started_at" => Quests::getInstance()->players[$player->getName()]["quests"][$this->id]["started_at"],
			"completed_at" => time(),
			"progress" => Quests::getInstance()->players[$player->getName()]["quests"][$this->id]["progress"] ?? $this->getObjective()->getAmount()
		];

		foreach ($this->getReward() as $reward) {
			$reward->giveReward($player);
		}
	}

	public function startProgress(Player $player): void {
		if ($this->isDaily()) {
			Quests::getInstance()->players[$player->getName()]["daily_quests"][$this->id] = [
				"phase" => "progress",
				"started_at" => time(),
				"completed_at" => null,
				"progress" => 0
			];
			return;
		}

		Quests::getInstance()->players[$player->getName()]["quests"][$this->id] = [
			"phase" => "progress",
			"started_at" => time(),
			"completed_at" => null,
			"progress" => 0
		];

		if(isset($this->time)){
			Quests::getInstance()->players[$player->getName()]["quests"][$this->id]["end_time"] = strtotime("+{$this->time["d"]} days {$this->time["h"]} hours {$this->time["m"]} minutes {$this->time["s"]} seconds");
		}
	}

	public function cancelProgress(Player $player, bool $timeOut = false): void {
		$this->editProgress($player, "phase", $timeOut ? "timeout" : "cancelled");
		$this->editProgress($player, "cancelled_at", time());
	}

	public function editProgress(Player $player, string $key, int|string $value): void {
		Quests::getInstance()->players[$player->getName()]["quests"][$this->id][$key] = $value;
	}

	public function formatedDescription(Player $player, bool $progress = false): string {
		$rewards = "";
		$c = Quests::getInstance()->getConfig()->get("forms")["quest"];

		if ($this->isDaily()) {
			$rewards = $this->activeReward->getName() ?? "Active Reward";
		} else {
			foreach ($this->rewards as $reward) {
				$rewards .= $reward->getName() . "{$c["quest_multiply_color"]}, {$c["quest_multiply_endcolor"]}";
			}
		}

		return str_replace($progress ? self::PROGRESS : self::NO_PROGRESS, [$this->description, $rewards, $this->getTimeString(), $progress ? $this->getProgress($player, $this->isDaily()) : 0, $this->getObjective()->getAmount()], Quests::getInstance()->getConfig()->get("forms")["quest"][$progress ? "description_progress" : "description"]);
	}

	public function getTimeString(): string {
		if ($this->time === null) {
			return Quests::getInstance()->getConfig()->get("forms")["no_duration"];
		}

		$timezone = Quests::getInstance()->getConfig()->get("timecode");
		return $this->time["d"] . $timezone["d"] . $this->time["h"] . $timezone["h"] . $this->time["m"] . $timezone["m"] . $this->time["s"] . $timezone["s"];
	}

	public function getObjective(): Objective {
		return $this->objective;
	}

	public function getProgress(Player $player, bool $daily = false): int {
		return Quests::getInstance()->players[$player->getName()][$daily ? "daily_quests" : "quests"][$this->id]["progress"];
	}

	public function addProgress(Player $player, int $progress = 1, bool $daily = false): void {
		if($this->getProgress($player, $daily) >= $this->getObjective()->getAmount()) {
			$player->sendTip(str_replace(["{QUEST_NAME}"], [$this->getName()], Quests::getInstance()->getConfig()->get("tips")["progress_end"]));
			return;
		}

		Quests::getInstance()->players[$player->getName()][$daily ? "daily_quests" : "quests"][$this->id]["progress"] += $progress;
		$player->sendTip(str_replace(["{QUEST_NAME}", "{COUNT}"], [$this->getName(), $progress], Quests::getInstance()->getConfig()->get("tips")["progress"]));
	}

	public function delProgress(Player $player, int $progress = 1, bool $daily = false): void {
		Quests::getInstance()->players[$player->getName()][$daily ? "daily_quests" : "quests"][$this->id]["progress"] -= $progress;
	}

	public function getType(bool $formated = false): string {
		return $formated ? Quests::getInstance()->getConfig()->get("messages")["types"][$this->type] : $this->type;
	}

	private function getId(): int {
		return $this->id;
	}
}