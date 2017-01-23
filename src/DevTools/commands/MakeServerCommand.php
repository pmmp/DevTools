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

class MakeServerCommand extends DevToolsCommand{

	public function __construct(DevTools $plugin){
		parent::__construct("makeserver", $plugin);
		$this->setUsage("/makeserver [no-compress] [silent]");
		$this->setDescription("Creates a PocketMine-MP Phar");
		$this->setPermission("devtools.command.makeserver");
	}

	public function execute(CommandSender $sender, $commandLabel, array $args){
		$server = $sender->getServer();
		$pharPath = $this->getPlugin()->getDataFolder() . DIRECTORY_SEPARATOR . $server->getName() . "_" . $server->getPocketMineVersion() . ".phar";
		if(file_exists($pharPath)){
			$sender->sendMessage("Phar file already exists, overwriting...");
			@unlink($pharPath);
		}
		$silent = in_array("silent", $args);
		$compress = !in_array("no-compress", $args);

		$phar = new \Phar($pharPath);
		$phar->setMetadata([
			"name" => $server->getName(),
			"version" => $server->getPocketMineVersion(),
			"api" => $server->getApiVersion(),
			"minecraft" => $server->getVersion(),
			"creationDate" => time()
		]);
		$phar->setStub('<?php define("pocketmine\\\\PATH", "phar://". __FILE__ ."/"); require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php");  __HALT_COMPILER();');
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->startBuffering();

		$filePath = substr(\pocketmine\PATH, 0, 7) === "phar://" ? \pocketmine\PATH : realpath(\pocketmine\PATH) . "/";
		$filePath = rtrim(str_replace("\\", "/", $filePath), "/") . "/";
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath . "src")) as $file){
			$path = ltrim(str_replace(["\\", $filePath], ["/", ""], $file), "/");
			if($path{0} === "." or strpos($path, "/.") !== false or substr($path, 0, 4) !== "src/"){
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

		$sender->sendMessage($server->getName() . " " . $server->getPocketMineVersion() . " Phar file has been created in " . $pharPath);

		return true;
	}
}