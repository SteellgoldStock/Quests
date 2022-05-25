<?php

namespace steellgold\quests\instances;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\lang\Language;
use pocketmine\player\Player;
use steellgold\quests\Quests;

class Reward {

	public function __construct(
		public string  $name,
		public ?Item   $item,
		public ?string $commandToExecute,
		public ?int    $amount,
		public ?int    $day = 0
	) {
	}

	public static function fromStdClass($reward): Reward {
		if (isset($reward->item)) {
			$item = ItemFactory::getInstance()->get($reward->item->id, $reward->item->meta ?? 0, $reward->amount ?? 1);
		}

		return new Reward(
			$reward->name ?? "Reward Name",
			$item ?? null,
			$reward->commandToExecute ?? null,
			$reward->amount,
			$reward->day
		);
	}

	public function getName(): string {
		return $this->name;
	}

	public function getAmount(): ?int {
		return $this->amount;
	}

	public function getCommandToExecute(): ?string {
		return $this->commandToExecute;
	}

	public function getDay(): ?int {
		return $this->day ?? 0;
	}

	public function getItem(): ?Item {
		return $this->item;
	}

	public function giveReward(Player $player): void {
		if ($this->item !== null) {
			$player->getInventory()->addItem($this->item->setCount($this->amount));
		}

		if ($this->commandToExecute !== null) {
			$player->getServer()->dispatchCommand(new ConsoleCommandSender(Quests::getInstance()->getServer(), new Language("fra")), str_replace("{player}", $player->getName(), $this->commandToExecute));
		}
	}
}