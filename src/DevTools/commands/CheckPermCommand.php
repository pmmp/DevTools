<?php

/*
 * DevTools plugin for PocketMine-MP
 * Copyright (C) 2017 PocketMine Team <https://github.com/PocketMine/DevTools>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

namespace DevTools\commands;

use DevTools\DevTools;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\permission\Permission;
use pocketmine\utils\TextFormat;

class CheckPermCommand extends DevToolsCommand{
	public function __construct(DevTools $plugin){
		parent::__construct("checkperm", $plugin);
		$this->setUsage("/checkperm <node> [playerName]");
		$this->setDescription("Checks a permission value for the target player, or the current sender");
		$this->setPermission("devtools.command.checkperm;devtools.command.checkperm.other");
	}

	public function execute(CommandSender $sender, $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return false;
		}

		if(count($args) === 0){
			$this->sendUsage($sender);
			return true;
		}

		$target = $sender;

		$node = strtolower($args[0]);
		if(isset($args[1])){
			if(($player = $this->getPlugin()->getServer()->getPlayer($args[1])) instanceof Player){
				$target = $player;
			}else{
				return false;
			}
		}

		if($target !== $sender and !$sender->hasPermission("devtools.command.checkperm.other")){
			$sender->sendMessage(TextFormat::RED . "You do not have permissions to check other players.");
			return true;
		}else{
			$sender->sendMessage(TextFormat::GREEN . "---- " . TextFormat::WHITE . "Permission node " . $node . TextFormat::GREEN . " ----");
			$perm = $this->getPlugin()->getServer()->getPluginManager()->getPermission($node);
			if($perm instanceof Permission){
				$desc = TextFormat::GOLD . "Description: " . TextFormat::WHITE . $perm->getDescription() . "\n";
				$desc .= TextFormat::GOLD . "Default: " . TextFormat::WHITE . $perm->getDefault() . "\n";
				$children = "";
				foreach($perm->getChildren() as $name => $true){
					$children .= $name . ", ";
				}
				$desc .= TextFormat::GOLD . "Children: " . TextFormat::WHITE . substr($children, 0, -2) . "\n";
			}else{
				$desc = TextFormat::RED . "Permission does not exist\n";
				$desc .= TextFormat::GOLD . "Default: " . TextFormat::WHITE . Permission::$DEFAULT_PERMISSION . "\n";
			}
			$sender->sendMessage($desc);
			$sender->sendMessage(TextFormat::YELLOW . $target->getName() . TextFormat::WHITE . " has it set to " . ($target->hasPermission($node) === true ? TextFormat::GREEN . "true" : TextFormat::RED . "false"));
			return true;
		}
	}
}