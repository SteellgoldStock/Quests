<?php

namespace steellgold\quests\instances;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;

class Objective {

	/**
	 * @param Block|null $block
	 * @param Item|null $item
	 * @param int|null $amount
	 */
	public function __construct(
		public ?Block $block = null,
		public ?Item  $item = null,
		public ?int   $amount = null,
	) {
	}

	public static function fromStdClass($objective): Objective {
		return new Objective(
			isset($objective->block->id) ? BlockFactory::getInstance()->get($objective->block->id, $objective->block->meta ?? 0) : null,
			isset($objective->item->id) ? ItemFactory::getInstance()->get($objective->item->id, $objective->item->meta ?? 0) : null,
			$objective->amount
		);
	}

	public function getBlock(): ?Block {
		return $this->block;
	}

	public function getItem(): ?Item {
		return $this->item;
	}

	public function getAmount(): ?int {
		return $this->amount;
	}

	public function setBlock(?Block $block): void {
		$this->block = $block;
	}

	public function setItem(?Item $item): void {
		$this->item = $item;
	}

	public function setAmount(?int $amount): void {
		$this->amount = $amount;
	}
}