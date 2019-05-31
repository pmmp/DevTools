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

namespace FolderPluginLoader;

use ClassLoader;
use pocketmine\plugin\PharPluginLoader;
use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginManager;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function is_file;
use function realpath;
use function rtrim;
use function scandir;
use const DIRECTORY_SEPARATOR;
use const SCANDIR_SORT_NONE;

final class FolderPluginLoader{
	/**
	 * Load script plugins in the directory $dir into the provided PluginManager
	 *
	 * @param PluginManager $manager
	 * @param ClassLoader   $classLoader
	 * @param string        $dir
	 */
	public static function scanPlugins(PluginManager $manager, ClassLoader $classLoader, string $dir) : void{
		foreach(scandir($dir, SCANDIR_SORT_NONE) as $file){
			$file = $dir . DIRECTORY_SEPARATOR . $file;
			if(is_dir($file)){
				self::loadPlugin($manager, $classLoader, $file);
			}
		}
	}

	public static function loadPlugin(PluginManager $manager, ClassLoader $classLoader, string $file) : bool{
		$description = self::getPluginDescription($file);
		if($description === null){
			return false;
		}
		$file = realpath($file);

		PharPluginLoader::loadClassical($manager, $classLoader, $file, $description);
		return true;
	}

	/**
	 * Gets the PluginDescription from the file
	 *
	 * @param string $file
	 *
	 * @return null|PluginDescription
	 */
	public static function getPluginDescription(string $file) : ?PluginDescription{
		$file = rtrim($file, "\\/") . "/";
		if(is_file($file . "plugin.yml")){
			return new PluginDescription(file_get_contents($file . "plugin.yml"));
		}

		return null;
	}
}
