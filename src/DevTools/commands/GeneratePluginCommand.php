<?php

/*
 * DevTools plugin for PocketMine-MP
 * Copyright (C) 2017 PocketMine Team <https://github.com/pmmp/PocketMine-DevTools>
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

declare(strict_types=1);

namespace DevTools\commands;


use DevTools\DevTools;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class GeneratePluginCommand extends DevToolsCommand{

	public function __construct(DevTools $plugin){
		parent::__construct("genplugin", $plugin);
		$this->setUsage("/genplugin <pluginName> <authorName>");
		$this->setDescription("Generates skeleton files for a plugin");
		$this->setPermission("devtools.command.genplugin");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->getPlugin()->isEnabled()){
			return false;
		}

		if(!$this->testPermission($sender)){
			return false;
		}

		if(count($args) < 2){
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->usageMessage);
			return true;
		}

		list($pluginName, $author) = $args;
		if(preg_match("/[^A-Za-z0-9_-]/", $pluginName) !== 0 or preg_match("/[^A-Za-z0-9_-]/", $author) !== 0){
			$sender->sendMessage(TextFormat::RED . "Plugin name and author name must contain only letters, numbers, underscores and dashes.");
			return true;
		}

		$rootDirectory = $this->getPlugin()->getServer()->getPluginPath() . $pluginName . DIRECTORY_SEPARATOR;
		if($this->getPlugin()->getServer()->getPluginManager()->getPlugin($pluginName) !== null or file_exists($rootDirectory)){
			$sender->sendMessage(TextFormat::RED . "A plugin with this name already exists on the server. Please choose a different name or remove the other plugin.");
			return true;
		}

		$namespace = self::correctNamespacePart($author) . "\\" . self::correctNamespacePart($pluginName);
		$namespacePath = "src" . DIRECTORY_SEPARATOR . str_replace("\\", DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;

		mkdir($rootDirectory . $namespacePath, 0755, true); //create all the needed directories

		$pluginSkeletonTemplateDir = $this->getPlugin()->getDataFolder() . DIRECTORY_SEPARATOR . "plugin_skeleton" . DIRECTORY_SEPARATOR;

		$this->getPlugin()->saveResource("plugin_skeleton/plugin.yml", true);
		$this->getPlugin()->saveResource("plugin_skeleton/Main.php", true);

		$replace = [
			"%{PluginName}" => $pluginName,
			"%{ApiVersion}" => $this->getPlugin()->getServer()->getApiVersion(),
			"%{AuthorName}" => $author,
			"%{Namespace}" => $namespace
		];

		file_put_contents($rootDirectory . "plugin.yml", str_replace(
			array_keys($replace),
			array_values($replace),
			file_get_contents($pluginSkeletonTemplateDir . "plugin.yml")
		));

		file_put_contents($rootDirectory . $namespacePath . "Main.php", str_replace(
			"#%{Namespace}", "namespace " . $namespace . ";",
			file_get_contents($pluginSkeletonTemplateDir . "Main.php")
		));

		$sender->sendMessage("Created skeleton plugin $pluginName in " . $rootDirectory);
		return true;
	}

	private static function correctNamespacePart(string $part) : string{
		if(ctype_digit($part{0})){
			$part = "_" . $part;
		}
		return str_replace("-", "_", $part);
	}
}
