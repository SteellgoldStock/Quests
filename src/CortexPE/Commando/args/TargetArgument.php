<?php

namespace CortexPE\Commando\args;

use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;

class TargetArgument extends BaseArgument {

	public function __construct(string $name, bool $optional = false) {
		parent::__construct($name, $optional);
	}

	public function getNetworkType(): int {
		return AvailableCommandsPacket::ARG_TYPE_TARGET;
	}

	public function getTypeName(): string {
		return "target";
	}

	public function canParse(string $testString, CommandSender $sender): bool {
		return true;
	}

	public function parse(string $argument, CommandSender $sender): string {
		return $argument;
	}
}