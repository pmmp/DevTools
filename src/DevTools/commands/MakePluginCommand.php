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
use FolderPluginLoader\FolderPluginLoader;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class MakePluginCommand extends DevToolsCommand{

	public function __construct(DevTools $plugin){
		parent::__construct("makeplugin", $plugin);
		$this->setUsage("/makeplugin <plugin name> [no-compress] [silent]");
		$this->setDescription("Creates a Phar plugin from source code");
		$this->setPermission("devtools.command.makeplugin");
	}

	public function execute(CommandSender $sender, $commandLabel, array $args){
		$pluginName = trim(implode(" ", $args));
		$silent = in_array("silent", $args);
		$compress = !in_array("no-compress", $args);
		if($pluginName === "FolderPluginLoader"){
			$pharPath = $this->getPlugin()->getDataFolder() . DIRECTORY_SEPARATOR . "FolderPluginLoader.phar";
			if(file_exists($pharPath)){
				$sender->sendMessage("Phar plugin already exists, overwriting...");
				@unlink($pharPath);
			}
			$phar = new \Phar($pharPath);

			$phar->setMetadata([
				"name" => "FolderPluginLoader",
				"version" => "1.0.1",
				"main" => "FolderPluginLoader\\Main",
				"api" => ["1.0.0", "2.0.0"],
				"depend" => [],
				"description" => "Loader of folder plugins",
				"authors" => ["PocketMine Team"],
				"website" => "https://github.com/PocketMine/DevTools",
				"creationDate" => time()
			]);
			$phar->setStub('<?php __HALT_COMPILER();');
			$phar->setSignatureAlgorithm(\Phar::SHA1);
			$phar->startBuffering();

			$phar->addFromString("plugin.yml", "name: FolderPluginLoader\nversion: 1.0.1\nmain: FolderPluginLoader\\Main\napi: [1.0.0, 2.0.0]\nload: STARTUP\n");
			$phar->addFile($this->getPlugin()->getFile() . "src/FolderPluginLoader/FolderPluginLoader.php", "src/FolderPluginLoader/FolderPluginLoader.php");
			$phar->addFile($this->getPlugin()->getFile() . "src/FolderPluginLoader/Main.php", "src/FolderPluginLoader/Main.php");

			if($compress){
				foreach($phar as $file => $finfo){
					/** @var \PharFileInfo $finfo */
					if($finfo->getSize() > (1024 * 512)){
						$finfo->compress(\Phar::GZ);
					}
				}
			}
			$phar->stopBuffering();
			$sender->sendMessage("Folder plugin loader has been created on " . $pharPath);
		}else{
			if($pluginName === "" or !(($plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName)) instanceof Plugin)){
				$sender->sendMessage(TextFormat::RED . "Invalid plugin name, check the name case.");
				return true;
			}else{
				$description = $plugin->getDescription();

				if(!($plugin->getPluginLoader() instanceof FolderPluginLoader)){
					$sender->sendMessage(TextFormat::RED . "Plugin " . $description->getName() . " is not in folder structure.");
					return true;
				}

				$pharPath = $this->getPlugin()->getDataFolder() . DIRECTORY_SEPARATOR . $description->getName() . "_v" . $description->getVersion() . ".phar";
			}

			if(file_exists($pharPath)){
				$sender->sendMessage("Phar plugin already exists, overwriting...");
				@unlink($pharPath);
			}
			$phar = new \Phar($pharPath);

			$phar->setMetadata([
				"name" => $description->getName(),
				"version" => $description->getVersion(),
				"main" => $description->getMain(),
				"api" => $description->getCompatibleApis(),
				"depend" => $description->getDepend(),
				"description" => $description->getDescription(),
				"authors" => $description->getAuthors(),
				"website" => $description->getWebsite(),
				"creationDate" => time()
			]);

			//TODO: add support for custom stubs
			if($description->getName() === "DevTools"){
				$phar->setStub('<?php require("phar://". __FILE__ ."/src/DevTools/ConsoleScript.php"); __HALT_COMPILER();');
			}else{
				$phar->setStub('<?php echo "PocketMine-MP plugin ' . $description->getName() . ' v' . $description->getVersion() . '\nThis file has been generated using DevTools v' . $this->getPlugin()->getDescription()->getVersion() . ' at ' . date("r") . '\n----------------\n";if(extension_loaded("phar")){$phar = new \Phar(__FILE__);foreach($phar->getMetadata() as $key => $value){echo ucfirst($key).": ".(is_array($value) ? implode(", ", $value):$value)."\n";}} __HALT_COMPILER();');
			}

			$phar->setSignatureAlgorithm(\Phar::SHA1);
			$reflection = new \ReflectionClass("pocketmine\\plugin\\PluginBase");
			$file = $reflection->getProperty("file");
			$file->setAccessible(true);
			$filePath = rtrim(str_replace("\\", "/", $file->getValue($plugin)), "/") . "/";
			$phar->startBuffering();
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath)) as $file){
				$path = ltrim(str_replace(["\\", $filePath], ["/", ""], $file), "/");
				if($path{0} === "." or strpos($path, "/.") !== false){
					continue;
				}
				$phar->addFile($file, $path);
				if(!$silent){
					$sender->sendMessage("[DevTools] Adding $path");
				}
			}

			if($compress){
				foreach($phar as $file => $finfo){
					/** @var \PharFileInfo $finfo */
					if($finfo->getSize() > (1024 * 512)){
						$finfo->compress(\Phar::GZ);
					}
				}
			}
			$phar->stopBuffering();
			$sender->sendMessage("Phar plugin " . $description->getName() . " v" . $description->getVersion() . " has been created in " . $pharPath);
		}

		return true;
	}
}