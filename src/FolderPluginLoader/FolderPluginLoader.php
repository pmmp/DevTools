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

use pocketmine\plugin\loader\AbstractPluginLoader;
use pocketmine\plugin\manifest\YamlManifestLoader;
use function file_exists;
use function is_dir;

class FolderPluginLoader extends AbstractPluginLoader{

	public function __construct(){
		$this->addManifestLoader(YamlManifestLoader::class);
	}

	public function canLoadPlugin(string $path) : bool{
		return is_dir($path) and file_exists($path . "/plugin.yml") and file_exists($path . "/src/");
	}
}
