<?php

namespace steellgold\quests\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use steellgold\quests\forms\QuestsForms;
use steellgold\quests\Quests;
use steellgold\quests\task\UpdateQuestTask;

class QuestsCommand extends Command {

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if(!$sender instanceof Player) return;

		if($sender->hasPermission("quests.reroll")) {
			if(isset($args[0])) {
				if($args[0] === "reroll") {
					UpdateQuestTask::generate(true);
					$sender->sendMessage(Quests::getPrefix() . Quests::getInstance()->getConfig()->get("messages")["daily_quest"]["rerolled"]);
					return;
				}else{
					$sender->sendForm(QuestsForms::getHomeForm($sender));
				}
			}else{
				$sender->sendForm(QuestsForms::getHomeForm($sender));
			}
		}else{
			$sender->sendForm(QuestsForms::getHomeForm($sender));
		}
	}
}