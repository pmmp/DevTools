<?php

/*
 * DevTools plugin for PocketMine-MP
 * Copyright (C) 2014, 2017 PocketMine Team <https://github.com/PocketMine/DevTools>
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

namespace DevTools;

use DevTools\commands\CheckPermCommand;
use DevTools\commands\ExtractPluginCommand;
use DevTools\commands\MakePluginCommand;
use DevTools\commands\MakeServerCommand;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;

class DevTools extends PluginBase{

	public function onLoad(){
		$map = $this->getServer()->getCommandMap();
		$map->register("devtools", new CheckPermCommand($this));
		$map->register("devtools", new ExtractPluginCommand($this));
		$map->register("devtools", new MakePluginCommand($this));
		$map->register("devtools", new MakeServerCommand($this));
	}

	public function onEnable(){
		@mkdir($this->getDataFolder());

		$this->getServer()->getPluginManager()->registerInterface("FolderPluginLoader\\FolderPluginLoader");
		$this->getServer()->getPluginManager()->loadPlugins($this->getServer()->getPluginPath(), ["FolderPluginLoader\\FolderPluginLoader"]);
		$this->getLogger()->info("Registered folder plugin loader");
		$this->getServer()->enablePlugins(PluginLoadOrder::STARTUP);
	}

	public function getFile(){
		return parent::getFile();
	}
}