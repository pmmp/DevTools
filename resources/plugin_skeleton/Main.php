<?php

declare(strict_types=1);

#%{Namespace}

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

class Main extends PluginBase{

	public function onEnable() : void{
		$this->getLogger()->info("Hello World!");
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case "examplecommand":
				$sender->sendMessage("Example command output");
				return true;
			default:
				return false;
		}
	}

	public function onDisable() : void{
		$this->getLogger()->info("Bye");
	}
}
