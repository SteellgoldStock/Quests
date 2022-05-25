<?php

namespace steellgold\quests\commands;

use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use steellgold\quests\forms\QuestsForms;

class QuestsCommand extends BaseCommand {

	protected function prepare(): void {
		// TODO: Implement prepare() method.
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if($sender instanceof Player) $sender->sendForm(QuestsForms::getQuestsForm());
	}
}