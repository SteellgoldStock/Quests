<?php

namespace steellgold\quests\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use steellgold\quests\forms\QuestsForms;

class QuestsCommand extends Command {

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if($sender instanceof Player) $sender->sendForm(QuestsForms::getHomeForm($sender));
	}
}