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

const VERSION = "1.12.4";

$opts = getopt("", ["make:", "relative:", "out:", "entry:", "compress", "stub:"]);

if(!isset($opts["make"])){
	echo "== PocketMine-MP DevTools CLI interface ==" . PHP_EOL . PHP_EOL;
	echo "Usage: " . PHP_BINARY . " -dphar.readonly=0 " . $argv[0] . " --make <sourceFolder1[,sourceFolder2[,sourceFolder3...]]> --relative <relativePath> --entry \"relativeSourcePath.php\" --out <pharName.phar>" . PHP_EOL;
	exit(0);
}

if(ini_get("phar.readonly") == 1){
	echo "Set phar.readonly to 0 with -dphar.readonly=0" . PHP_EOL;
	exit(1);
}

$includedPaths = explode(",", $opts["make"]);
array_walk($includedPaths, function(&$path, $key){
	$realPath = realpath($path);
	if($realPath === false){
		echo "[ERROR] make directory `$path` does not exist or permission denied" . PHP_EOL;
		exit(1);
	}

	//Convert to absolute path for base path detection
	$path = rtrim(str_replace("\\", "/", $realPath), "/") . "/";
});

$basePath = "";
if(!isset($opts["relative"])){
	if(count($includedPaths) > 1){
		echo "You must specify a relative path with --relative <path> to be able to include multiple directories" . PHP_EOL;
		exit(1);
	}else{
		$basePath = rtrim(str_replace("\\", "/", realpath(array_shift($includedPaths))), "/") . "/";
	}
}else{
	$basePath = rtrim(str_replace("\\", "/", realpath($opts["relative"])), "/") . "/";
}

//Convert included paths back to relative after we decide what the base path is
$includedPaths = array_filter(array_map(function(string $path) use ($basePath) : string{
	return str_replace($basePath, '', $path);
}, $includedPaths), function(string $v) : bool{
	return $v !== '';
});

$pharName = $opts["out"] ?? "output.phar";
$stubPath = $opts["stub"] ?? "stub.php";

if(!is_dir($basePath)){
	echo $basePath . " is not a folder" . PHP_EOL;
	exit(1);
}

echo PHP_EOL;

if(file_exists($pharName)){
	echo "$pharName already exists, overwriting..." . PHP_EOL;
	@unlink($pharName);
}

echo "Creating " . $pharName . "..." . PHP_EOL;
$start = microtime(true);
$phar = new \Phar($pharName);

if(file_exists($basePath . $stubPath)){
	echo "Using stub " . $basePath . $stubPath . PHP_EOL;
	$phar->setStub('<?php require("phar://" . __FILE__ . "/' . $stubPath . '"); __HALT_COMPILER();');
}elseif(isset($opts["entry"])){
	$entry = addslashes(str_replace("\\", "/", $opts["entry"]));
	echo "Setting entry point to " . $entry . PHP_EOL;
	$phar->setStub('<?php require("phar://" . __FILE__ . "/' . $entry . '"); __HALT_COMPILER();');
}else{
	if(file_exists($basePath . "plugin.yml")){
		$metadata = yaml_parse_file($basePath . "plugin.yml");
	}else{
		echo "Missing entry point or plugin.yml" . PHP_EOL;
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
echo "Adding files..." . PHP_EOL;
$maxLen = 0;
$count = 0;

function preg_quote_array(array $strings, string $delim = null) : array{
	return array_map(function(string $str) use ($delim) : string{ return preg_quote($str, $delim); }, $strings);
}

//If paths contain any of these, they will be excluded
$excludedSubstrings = [
	"/.", //"Hidden" files, git information etc
	$pharName //don't add the phar to itself
];

$regex = sprintf('/^(?!.*(%s))^%s(%s).*/i',
	implode('|', preg_quote_array($excludedSubstrings, '/')), //String may not contain any of these substrings
	preg_quote($basePath, '/'), //String must start with this path...
	implode('|', preg_quote_array($includedPaths, '/')) //... and must be followed by one of these relative paths, if any were specified. If none, this will produce a null capturing group which will allow anything.
);

$count = count($phar->buildFromDirectory($basePath, $regex));
echo "Added $count files" . PHP_EOL;

$phar->stopBuffering();

echo PHP_EOL . "Done in " . round(microtime(true) - $start, 3) . "s" . PHP_EOL;
exit(0);