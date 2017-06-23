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

const VERSION = "1.12.1";

$opts = getopt("", ["make:", "relative:", "out:", "entry:", "compress", "stub:"]);

if(!isset($opts["make"])){
	echo "== PocketMine-MP DevTools CLI interface ==\n\n";
	echo "Usage: " . PHP_BINARY . " -dphar.readonly=0 " . $argv[0] . " --make <sourceFolder1[,sourceFolder2[,sourceFolder3...]]> --relative <relativePath> --entry \"relativeSourcePath.php\" --out <pharName.phar>\n";
	exit(0);
}

if(ini_get("phar.readonly") == 1){
	echo "Set phar.readonly to 0 with -dphar.readonly=0\n";
	exit(1);
}

$makePaths = explode(",", $opts["make"]);
array_walk($makePaths, function(&$path, $key){
	$realPath = realpath($path);
	if($realPath === false){
		echo "[ERROR] make directory `$path` does not exist or permission denied" . PHP_EOL;
		exit(1);
	}
	$path = rtrim(str_replace("\\", "/", $realPath), "/") . "/";
});

$relativePath = "";
if(!isset($opts["relative"])){
	if(count($makePaths) > 1){
		echo "You must specify a relative path with --relative <path> to be able to include multiple directories" . PHP_EOL;
		exit(1);
	}else{
		$relativePath = $makePaths[0];
	}
}else{
	$relativePath = rtrim(str_replace("\\", "/", realpath($opts["relative"])), "/") . "/";
}

$pharName = $opts["out"] ?? "output.phar";
$stubPath = $opts["stub"] ?? "stub.php";

if(!is_dir($relativePath)){
	echo $relativePath . " is not a folder\n";
	exit(1);
}

echo "\nCreating " . $pharName . "...\n";
$phar = new \Phar($pharName);

if(file_exists($relativePath . $stubPath)){
	echo "Using stub " . $relativePath . $stubPath . "\n";
	$phar->setStub('<?php require("phar://" . __FILE__ . "/' . $stubPath . '"); __HALT_COMPILER();');
}elseif(isset($opts["entry"])){
	$entry = addslashes(str_replace("\\", "/", $opts["entry"]));
	echo "Setting entry point to " . $entry . "\n";
	$phar->setStub('<?php require("phar://" . __FILE__ . "/' . $entry . '"); __HALT_COMPILER();');
}else{
	if(file_exists($relativePath . "plugin.yml")){
		$metadata = yaml_parse_file($relativePath . "plugin.yml");
	}else{
		echo "Missing entry point or plugin.yml\n";
		exit(1);
	}

	$phar->setMetadata([
		"name" => $metadata["name"],
		"version" => $metadata["version"],
		"main" => $metadata["main"],
		"api" => $metadata["api"],
		"depend" => ($metadata["depend"] ?? ""),
		"description" => ($metadata["description"] ?? ""),
		"authors" => ($metadata["authors"] ?? ""),
		"website" => ($metadata["website"] ?? ""),
		"creationDate" => time()
	]);

	$phar->setStub('<?php echo "PocketMine-MP plugin ' . $metadata["name"] . ' v' . $metadata["version"] . '\nThis file has been generated using DevTools v" . $version . " at ' . date("r") . '\n----------------\n";if(extension_loaded("phar")){$phar = new \Phar(__FILE__);foreach($phar->getMetadata() as $key => $value){echo ucfirst($key).": ".(is_array($value) ? implode(", ", $value):$value)."\n";}} __HALT_COMPILER();');
}

$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->startBuffering();
echo "Adding files...\n";
$maxLen = 0;
$count = 0;
foreach($makePaths as $folderPath){
	foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folderPath)) as $file){
		$path = rtrim(str_replace(["\\", $relativePath], ["/", ""], $file), "/");
		if($path{0} === "." or strpos($path, "/.") !== false){
			continue;
		}
		$phar->addFile($file, $path);
		if(strlen($path) > $maxLen){
			$maxLen = strlen($path);
		}
		echo "\r[" . (++$count) . "] " . str_pad($path, $maxLen, " ");
	}
}

$phar->stopBuffering();

echo "\nDone!\n";
exit(0);