<?php

/*
 * DevTools plugin for PocketMine-MP
 * Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/DevTools>
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

namespace DevTools;

use DevTools\commands\ExtractPluginCommand;
use DevTools\commands\GeneratePluginCommand;
use FolderPluginLoader\FolderPluginLoader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class DevTools extends PluginBase{

	public function onLoad() : void{
		require_once __DIR__ . "/ConsoleScript.php";
		$map = $this->getServer()->getCommandMap();
		$map->register("devtools", new ExtractPluginCommand($this));
		$map->register("devtools", new GeneratePluginCommand($this));
	}

	public function onEnable() : void{
		@mkdir($this->getDataFolder());

		$this->getServer()->getPluginManager()->registerInterface(new FolderPluginLoader($this->getServer()->getLoader()));
		$this->getServer()->getPluginManager()->loadPlugins($this->getServer()->getPluginPath(), [FolderPluginLoader::class]);
		$this->getLogger()->info("Registered folder plugin loader");
		$this->getServer()->enablePlugins(PluginLoadOrder::STARTUP);

	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case "makeplugin":
				if(isset($args[0]) and $args[0] === "FolderPluginLoader"){
					return $this->makePluginLoader($sender);
				}elseif(isset($args[0]) and $args[0] === "*"){
					$plugins = $this->getServer()->getPluginManager()->getPlugins();
					$succeeded = $failed = [];
					$skipped = 0;
					foreach($plugins as $plugin){
						if(!$plugin->getPluginLoader() instanceof FolderPluginLoader){
							$skipped++;
							continue;
						}
						if($this->makePluginCommand($sender, [$plugin->getName()])){
							$succeeded[] = $plugin->getName();
						}else{
							$failed[] = $plugin->getName();
						}
					}
					if(count($failed) > 0){
						$sender->sendMessage(TextFormat::RED . count($failed) . " plugin"
							. (count($failed) === 1 ? "" : "s") . " failed to build: " . implode(", ", $failed));
					}
					if(count($succeeded) > 0){
						$sender->sendMessage(TextFormat::GREEN . count($succeeded) . "/" . (count($plugins) - $skipped) . " plugin"
							. ((count($plugins) - $skipped) === 1 ? "" : "s") . " successfully built: " . implode(", ", $succeeded));
					}
					return true;
				}else{
					$this->makePluginCommand($sender, $args);
					return true;
				}
			case "makeserver":
				return $this->makeServerCommand($sender);
			case "checkperm":
				return $this->permissionCheckCommand($sender, $args);
			default:
				return false;
		}
	}

	private function permissionCheckCommand(CommandSender $sender, array $args) : bool{
		$target = $sender;
		if(!isset($args[0])){
			return false;
		}
		$node = strtolower($args[0]);
		if(isset($args[1])){
			if(($player = $this->getServer()->getPlayer($args[1])) instanceof Player){
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
			$perm = $this->getServer()->getPluginManager()->getPermission($node);
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

	private function makePluginLoader(CommandSender $sender) : bool{
		$pharPath = $this->getDataFolder() . "FolderPluginLoader.phar";
		if(file_exists($pharPath)){
			$sender->sendMessage("Phar plugin already exists, overwriting...");
			\Phar::unlinkArchive($pharPath);
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
		$phar->addFile($this->getFile() . "src/FolderPluginLoader/FolderPluginLoader.php", "src/FolderPluginLoader/FolderPluginLoader.php");
		$phar->addFile($this->getFile() . "src/FolderPluginLoader/Main.php", "src/FolderPluginLoader/Main.php");

		foreach($phar as $file => $finfo){
			/** @var \PharFileInfo $finfo */
			if($finfo->getSize() > (1024 * 512)){
				$finfo->compress(\Phar::GZ);
			}
		}
		$phar->stopBuffering();
		$sender->sendMessage("Folder plugin loader has been created on " . $pharPath);
		return true;
	}

	private function makePluginCommand(CommandSender $sender, array $args) : bool{
		$pluginName = trim(implode(" ", $args));
		if($pluginName === "" or !(($plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName)) instanceof Plugin)){
			$sender->sendMessage(TextFormat::RED . "Invalid plugin name, check the name case.");
			return false;
		}
		$description = $plugin->getDescription();

		if(!($plugin->getPluginLoader() instanceof FolderPluginLoader)){
			$sender->sendMessage(TextFormat::RED . "Plugin " . $description->getName() . " is not in folder structure.");
			return false;
		}

		$pharPath = $this->getDataFolder() . $description->getName() . "_v" . $description->getVersion() . ".phar";

		if($description->getName() === "DevTools"){
			$stub = sprintf(DEVTOOLS_REQUIRE_FILE_STUB, "src/DevTools/ConsoleScript.php");
		}else{
			$stub = sprintf(DEVTOOLS_PLUGIN_STUB, $description->getName(), $description->getVersion(), $this->getDescription()->getVersion(), date("r"));
		}

		$reflection = new \ReflectionClass(PluginBase::class);
		$file = $reflection->getProperty("file");
		$file->setAccessible(true);
		$filePath = realpath($file->getValue($plugin));
		assert(is_string($filePath));
		$filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		$metadata = generatePluginMetadataFromYml($filePath . "plugin.yml");
		assert($metadata !== null);

		$this->buildPhar($sender, $pharPath, $filePath, [], $metadata, $stub, \Phar::SHA1);

		$sender->sendMessage("Phar plugin " . $description->getName() . " v" . $description->getVersion() . " has been created on " . $pharPath);
		return true;
	}

	private function makeServerCommand(CommandSender $sender) : bool{
		if(strpos(\pocketmine\PATH, "phar://") === 0){
			$sender->sendMessage(TextFormat::RED . "This command can only be used on a server running from source code");
			return true;
		}

		$server = $sender->getServer();
		$pharPath = $this->getDataFolder() . $server->getName() . "_" . $server->getPocketMineVersion() . ".phar";

		$metadata = [
			"name" => $server->getName(),
			"version" => $server->getPocketMineVersion(),
			"api" => $server->getApiVersion(),
			"minecraft" => $server->getVersion(),
			"creationDate" => time(),
			"protocol" => ProtocolInfo::CURRENT_PROTOCOL
		];

		$stub = sprintf(DEVTOOLS_REQUIRE_FILE_STUB, "src/pocketmine/PocketMine.php");

		$filePath = realpath(\pocketmine\PATH) . DIRECTORY_SEPARATOR;
		$filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		$this->buildPhar($sender, $pharPath, $filePath, ['src', 'vendor', 'resources'], $metadata, $stub, \Phar::SHA1);

		$sender->sendMessage($server->getName() . " " . $server->getPocketMineVersion() . " Phar file has been created on " . $pharPath);

		return true;
	}

	private function buildPhar(CommandSender $sender, string $pharPath, string $basePath, array $includedPaths, array $metadata, string $stub, int $signatureAlgo = \Phar::SHA1) : void{
		foreach(buildPhar($pharPath, $basePath, $includedPaths, $metadata, $stub, $signatureAlgo, \Phar::GZ) as $line){
			$sender->sendMessage("[DevTools] $line");
		}
	}
}
