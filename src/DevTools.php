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
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerList;
use pocketmine\event\HandlerListManager;
use pocketmine\permission\Permissible;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionAttachmentInfo;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use function assert;
use function buildPhar;
use function count;
use function date;
use function generatePluginMetadataFromYml;
use function implode;
use function ini_get;
use function ksort;
use function php_ini_loaded_file;
use function realpath;
use function rtrim;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;
use const DEVTOOLS_PLUGIN_STUB;
use const DEVTOOLS_REQUIRE_FILE_STUB;
use const DIRECTORY_SEPARATOR;
use const SORT_STRING;

class DevTools extends PluginBase{

	private const EVENT_PRIORITY_NAMES = [
		EventPriority::LOWEST => "LOWEST",
		EventPriority::LOW => "LOW",
		EventPriority::NORMAL => "NORMAL",
		EventPriority::HIGH => "HIGH",
		EventPriority::HIGHEST => "HIGHEST",
		EventPriority::MONITOR => "MONITOR",
	];

	public function onLoad() : void{
		require_once __DIR__ . "/ConsoleScript.php";
		$map = $this->getServer()->getCommandMap();
		$map->register("devtools", new ExtractPluginCommand($this));
		$map->register("devtools", new GeneratePluginCommand($this));

		$this->getServer()->getPluginManager()->registerInterface(new FolderPluginLoader($this->getServer()->getLoader()));
		$this->getLogger()->info("Registered folder plugin loader");
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case "makeplugin":
				if(isset($args[0]) and $args[0] === "*"){
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
				}else{
					$this->makePluginCommand($sender, $args);
				}
				return true;
			case "checkperm":
				return $this->permissionCheckCommand($sender, $args);
			case "listperms":
				return $this->permissionListCommand($sender, $args);
			case "handlers":
				return $this->handlerListCommand($sender, $args);
			case "handlersbyplugin":
				return $this->handlerListByPluginCommand($sender, $args);
			default:
				return false;
		}
	}

	/**
	 * @param string[] $args
	 */
	private function permissionCheckCommand(CommandSender $sender, array $args) : bool{
		$target = $sender;
		if(!isset($args[0])){
			return false;
		}
		$node = strtolower($args[0]);
		if(isset($args[1])){
			if(($player = $this->getServer()->getPlayerByPrefix($args[1])) instanceof Player){
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
			$perm = PermissionManager::getInstance()->getPermission($node);
			if($perm instanceof Permission){
				$desc = TextFormat::GOLD . "Description: " . TextFormat::WHITE . $perm->getDescription() . "\n";
				$children = [];
				foreach($perm->getChildren() as $name => $isGranted){
					$children[] = ($isGranted ? TextFormat::GREEN : TextFormat::RED) . $name . TextFormat::WHITE;
				}
				$desc .= TextFormat::GOLD . "Children: " . TextFormat::WHITE . implode(", ", $children) . "\n";
			}else{
				$desc = TextFormat::RED . "Permission does not exist\n";
			}
			$sender->sendMessage($desc);
			$coloredName = TextFormat::YELLOW . $target->getName() . TextFormat::RESET;
			$sender->sendMessage(TextFormat::GOLD . "Permission info for $coloredName:");
			foreach($this->describePermissionSet($target, $node) as $line){
				$sender->sendMessage("- " . $line);
			}
			return true;
		}
	}

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	private function describePermissionSet(Permissible $sender, string $permission) : array{
		$permInfo = $sender->getEffectivePermissions()[$permission] ?? null;
		if($permInfo === null){
			return [
				TextFormat::RED . $permission . TextFormat::WHITE . " is not set (default " . TextFormat::RED . "false" . TextFormat::WHITE . ")"
			];
		}
		$result = [];

		while($permInfo !== null){
			$result[] = $this->describePermission($permInfo);
			$permInfo = $permInfo->getGroupPermissionInfo();
		}
		return $result;
	}

	private function describePermission(PermissionAttachmentInfo $permInfo) : string{
		$permColor = static function(PermissionAttachmentInfo $info, bool $dark) : string{
			if($info->getValue()){
				$color = $dark ? TextFormat::DARK_GREEN : TextFormat::GREEN;
			}else{
				$color = $dark ? TextFormat::DARK_RED : TextFormat::RED;
			}
			return sprintf("%s%s%s", $color, $info->getPermission(), TextFormat::WHITE);
		};
		$permValue = static function(bool $value) : string{
			return ($value ? TextFormat::GREEN . "true" : TextFormat::RED . "false") . TextFormat::WHITE;
		};

		$groupPermInfo = $permInfo->getGroupPermissionInfo();
		if($groupPermInfo !== null){
			return $permColor($permInfo, false) . " is set to " . $permValue($permInfo->getValue()) . " by " . $permColor($groupPermInfo, true);
		}else{
			$permOrigin = $permInfo->getAttachment();
			if($permOrigin !== null){
				$originName = "plugin " . TextFormat::GREEN . $permOrigin->getPlugin()->getName();
			}else{
				$originName = "base permission";
			}
			return $permColor($permInfo, false) . " is set to " . $permValue($permInfo->getValue()) . " explicitly by $originName" . TextFormat::WHITE;
		}
	}

	/**
	 * @param string[] $args
	 */
	private function permissionListCommand(CommandSender $sender, array $args) : bool{
		$target = $sender;
		if(isset($args[0])){
			if(($player = $this->getServer()->getPlayerByPrefix($args[0])) instanceof Player){
				$target = $player;
			}else{
				return false;
			}
		}

		if($target !== $sender and !$sender->hasPermission("devtools.command.listperms.other")){
			$sender->sendMessage(TextFormat::RED . "You do not have permissions to check other players.");
			return true;
		}else{
			$sender->sendMessage(TextFormat::GOLD . "--- Permissions assigned to " . TextFormat::YELLOW . $target->getName() . TextFormat::GOLD . " ---");
			foreach($target->getEffectivePermissions() as $permissionAttachmentInfo){
				$sender->sendMessage("- " . $this->describePermission($permissionAttachmentInfo));
			}
			return true;
		}
	}

	/**
	 * @param string[] $args
	 */
	private function makePluginCommand(CommandSender $sender, array $args) : bool{
		if(ini_get('phar.readonly') !== '0'){
			$sender->sendMessage(TextFormat::RED . "This command requires \"phar.readonly\" to be set to 0. Set it in " . php_ini_loaded_file() . " and restart the server.");
			return true;
		}
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

		$reflection = new \ReflectionClass(PluginBase::class);
		$file = $reflection->getProperty("file");
		$file->setAccessible(true);
		$pfile = rtrim($file->getValue($plugin), '/');
		$filePath = realpath($pfile);
		if($filePath === false){
			$sender->sendMessage(TextFormat::RED . "Plugin " . $description->getName() . " not found at $pfile (maybe deleted?)");
			return false;
		}
		$filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		$metadata = generatePluginMetadataFromYml($filePath . "plugin.yml");
		assert($metadata !== null);

		if($description->getName() === "DevTools"){
			$stub = sprintf(DEVTOOLS_REQUIRE_FILE_STUB, "src/ConsoleScript.php");
		}else{
			$stubMetadata = [];
			foreach($metadata as $key => $value){
				$stubMetadata[] = addslashes(ucfirst($key) . ": " . (is_array($value) ? implode(", ", $value) : $value));
			}
			$stub = sprintf(DEVTOOLS_PLUGIN_STUB, $description->getName(), $description->getVersion(), $this->getDescription()->getVersion(), date("r"), implode("\n", $stubMetadata));
		}

		$this->buildPhar($sender, $pharPath, $filePath, [], $metadata, $stub, \Phar::SHA1);

		$sender->sendMessage("Phar plugin " . $description->getName() . " v" . $description->getVersion() . " has been created on " . $pharPath);
		return true;
	}

	/**
	 * @param string[] $includedPaths
	 * @param mixed[] $metadata
	 * @phpstan-param array<string, mixed> $metadata
	 */
	private function buildPhar(CommandSender $sender, string $pharPath, string $basePath, array $includedPaths, array $metadata, string $stub, int $signatureAlgo = \Phar::SHA1) : void{
		foreach(buildPhar($pharPath, $basePath, $includedPaths, $metadata, $stub, $signatureAlgo, \Phar::GZ) as $line){
			$sender->sendMessage("[DevTools] $line");
		}
	}

	private function describeHandlerList(CommandSender $sender, HandlerList $handlerList, string $className) : bool{
		$found = false;
		foreach(EventPriority::ALL as $priority){
			$priorityName = self::EVENT_PRIORITY_NAMES[$priority];
			for($currentList = $handlerList; $currentList !== null; $currentList = $currentList->getParent()){
				$handlers = $handlerList->getListenersByPriority($priority);
				if(count($handlers) === 0){
					continue;
				}

				if(!$found){
					$found = true;
					$sender->sendMessage("--- Handlers called by " . TextFormat::GREEN . $className . TextFormat::WHITE . " ---");
				}

				foreach($handlers as $handler){
					$sender->sendMessage(
						"- " .
						TextFormat::DARK_GREEN . Utils::getNiceClosureName($handler->getHandler()) . TextFormat::RESET .
						" in plugin " .
						TextFormat::DARK_GREEN . $handler->getPlugin()->getName() . TextFormat::RESET .
						" at priority " .
						TextFormat::DARK_GREEN . $priorityName . TextFormat::RESET .
						" (handles cancelled events: " .
						TextFormat::DARK_GREEN . ($handler->isHandlingCancelled() ? "yes" : "no") . TextFormat::RESET .
						")"
					);
				}
			}
		}

		return $found;
	}

	/**
	 * @param string[] $args
	 */
	private function handlerListCommand(CommandSender $sender, array $args) : bool{
		if(count($args) > 1){
			return false;
		}
		$all = HandlerListManager::global()->getAll();
		ksort($all, SORT_STRING);
		$found = false;
		foreach($all as $className => $handlerList){
			if(count($args) === 0 || str_contains($className, $args[0])){
				$found = true;
				$this->describeHandlerList($sender, $handlerList, $className);
			}
		}
		if(!$found){
			$sender->sendMessage(TextFormat::RED . "No event handlers found for any classes containing \"" . $args[0] . "\"");
		}
		return true;
	}

	/**
	 * @param string[] $args
	 */
	private function handlerListByPluginCommand(CommandSender $sender, array $args) : bool{
		if(count($args) !== 1){
			return false;
		}

		$plugin = Server::getInstance()->getPluginManager()->getPlugin($args[0]);
		if($plugin === null){
			$sender->sendMessage(TextFormat::RED . "No plugin found with name " . $args[0]);
			return true;
		}

		$sender->sendMessage("--- Event handlers registered by plugin " . TextFormat::GREEN . $plugin->getName() . TextFormat::WHITE . " ---");
		foreach(HandlerListManager::global()->getAll() as $className => $handlerList){
			foreach(EventPriority::ALL as $priority){
				$priorityName = self::EVENT_PRIORITY_NAMES[$priority];

				for($currentList = $handlerList; $currentList !== null; $currentList = $currentList->getParent()){
					$handlers = $handlerList->getListenersByPriority($priority);
					if(count($handlers) === 0){
						continue;
					}

					foreach($handlers as $handler){
						if($handler->getPlugin() !== $plugin){
							continue;
						}

						$sender->sendMessage(
							"- " .
							TextFormat::DARK_GREEN . Utils::getNiceClosureName($handler->getHandler()) . TextFormat::RESET .
							" handles event " .
							TextFormat::DARK_GREEN . $className . TextFormat::RESET .
							" at priority " .
							TextFormat::DARK_GREEN . $priorityName . TextFormat::RESET .
							" (handles cancelled events: " .
							TextFormat::DARK_GREEN . ($handler->isHandlingCancelled() ? "yes" : "no") . TextFormat::RESET .
							")"
						);
					}
				}
			}
		}

		return true;
	}
}
