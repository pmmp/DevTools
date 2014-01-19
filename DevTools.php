<?php

/*
__PocketMine Plugin__
name=Development Tools
description=A collection of tools so development for PocketMine-MP is easier
version=0.7.1
author=PocketMine Team
class=DevTools
apiversion=11,12
*/

/*

Small Changelog
===============

0.1:
- PocketMine-MP Alpha_1.2.1 release

0.2:
- PocketMine-MP Alpha_1.2.2 release
- Experimental code obfuscation

0.2.1:
- Fixes
- Obfuscation optional

0.2.2:
- Compatible with new API 6 version format

0.3:
- Eval PHP code from console /eval

0.3.1
- Fixes

0.3.2
- Fixes

0.4:
- Compilation fixes
- Finished code obfuscation

0.5:
- Added code snippets

0.6
- PMF plugins are now saved on the DevTools folder
- Compatible with Amai Beetroot

0.7
- API 12
- New obfuscation methods ;)

0.7.1
- Fixed obfuscation being applied on non-obfuscation mode


*/
		
class DevTools implements Plugin{
	public static $compileHeader = <<<HEADER
<?php
/**
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 *
 *
 * Check the complete source code at
 * http://www.pocketmine.net/ 
 *
 * PocketMine-MP {{version}} for Minecraft: PE {{mcpe}} @ {{time}}
 * 
 *
*/

define("POCKETMINE_COMPILE", true);

HEADER;

	private $api, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->config = new Config($this->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array(
			"snippets" => array(),
		));
		$this->api->console->register("compile", "[deflate]", array($this, "command"));
		$this->api->console->register("pmfplugin", "<PluginClassName> [identifier]", array($this, "command"));
		$this->api->console->register("eval", "<code...>", array($this, "command"));		
		$this->api->console->register("snippet", "<name> [code...]", array($this, "command"));
		$this->api->console->alias("pmfpluginob", "pmfplugin");
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "snippet":
				if($issuer !== "console"){					
					$output .= "Please run this command on the console.\n";
					break;
				}
				if(count($params) > 1){
					$s = $this->config->get("snippets");
					$index = array_shift($params);
					$s[strtolower($index)] = base64_encode(implode(" ", $params));
					$this->config->set("snippets", $s);
					$output .= "Snippet saved.\n";
				}else{
					if(isset($this->config->get("snippets")[strtolower($params[0])])){
						eval(base64_decode($this->config->get("snippets")[strtolower($params[0])]));
						$output .= "Snippet executed.\n";
					}else{
						$output .= "Snippet does not exists.\n";
					}
				}
				break;
			case "eval":
				if($issuer !== "console"){					
					$output .= "Please run this command on the console.\n";
					break;
				}
				$output .= eval(implode(" ", $params));
				break;
			case "compile":
				if($issuer !== "console"){
					$output .= "Must be run on the console.\n";
					break;
				}
				if(defined("POCKETMINE_COMPILE") and POCKETMINE_COMPILE === true){
					$output .= "Must be run in a pure Source PocketMine-MP.\n";
					break;
				}
				if(strtolower($params[0]) === "deflate"){
					$deflate = "";
				}else{
					$deflate = false;
				}
				$this->compilePM($output, $deflate);
				break;
			case "pmfplugin":
				$obfuscate = false;
				if($alias === "pmfpluginob"){
					$obfuscate = true;
				}
				if($issuer !== "console"){
					$output .= "Must be run on the console.\n";
					break;
				}
				if(!isset($params[0])){
					$output .= "Usage: /pmfplugin <PluginClassName> [identifier]\n";
					break;
				}
				$className = strtolower(trim($params[0]));
				if(isset($params[1])){
					$identifier = trim($params[1]);
				}else{
					$identifier = "";
				}
				$this->PMFPlugin($output, $className, $identifier, $obfuscate);
				break;
		}
		return $output;
	}
	
	private function randomCase($str){
		$len = strlen($str);
		$ret = "";
		for($i = 0; $i < $len; ++$i){
			switch(mt_rand(0, 2)){
				case 0:
					$ret .= strtoupper($str{$i});
					break;
				case 1:
					$ret .= strtolower($str{$i});
					break;
				case 2:
					$ret .= $str{$i};
					break;
			}
		}
		return $ret;
	}
	
	private function encodeString($str){
		$escape = false;
		$len = strlen($str);
		$ret = "";
		for($i = 0; $i < $len; ++$i){
			$c = $str{$i};
			if($c == "\n"){
				$ret .= $c;
				continue;
			}elseif($c === "\\"){
				$escape = true;
				$ret .= $c;
				continue;
			}elseif($escape == true){
				$escape = false;
				$ret .= $c;
				continue;
			}
			
			
			switch(mt_rand(0,2)){
				case 0:
					if(ord($c) >= 0x20 and ord($c) <= 0x7f and $c != "'" and $c != '"' and $c != '$'){
						$ret .= $c;
						break;
					}				
				case 1:
					$ret .= "\x".str_pad(dechex(ord($c)), 2, "0", STR_PAD_LEFT);
					break;
				case 2:
					$ret .= "\\".str_pad(decoct(ord($c)), 3, "0", STR_PAD_LEFT);
					break;
			}
		}
		return $ret;
	}
	
	private function PMFPlugin(&$output, $className, $data = array(), $obfuscate = false){
		$className = trim(strtolower($className));
		$info = false;
		foreach($this->api->plugin->getAll() as $identifier => $plugin){
			if($plugin[1]["class"] == $className){
				$info = $plugin[1];
				break;
			}
		}
		if($info === false){
			$output .= "The plugin class \"$className\" does not exist.\n";
			return false;
		}
		$pmf = new PMF($this->api->plugin->configPath($this).$info["name"].".pmf", true, 0x01);
		$pmf->write(chr(0x02));
		$pmf->write(Utils::writeShort(strlen($info["name"])).$info["name"]);
		$pmf->write(Utils::writeShort(strlen($info["version"])).$info["version"]);
		$pmf->write(Utils::writeShort(strlen($info["author"])).$info["author"]);
		$pmf->write(Utils::writeShort(strlen($info["apiversion"])).$info["apiversion"]);
		$pmf->write(Utils::writeShort(strlen($info["class"])).$info["class"]);
		$pmf->write(Utils::writeShort(strlen($identifier)).$identifier);
		$extra = "";
		foreach($data as $k => $v){
			$extra .= str_replace("=", "", base64_encode(str_replace(":", "_", $k).":".$v)).";";
		}
		$extra = gzdeflate($extra, 9);
		$pmf->write(Utils::writeInt(strlen($extra)).$extra); //Extra data
		$code = "";
		$lastspace = true;
		$info["code"] = str_replace(array("\\r", "\\n", "\\t"), array("\r", "\n", "\t"), $info["code"]);
		$src = token_get_all("<?php ".$info["code"]);
		$variables = array(
			'$this' => '$this',
		);
		$lastvar = false;
		$lastObjectVar = false;
		foreach($src as $index => $tag){
			if(!is_array($tag)){
				$code .= $tag;
				if($tag === ";"){
					$lastObjectVar = false;
				}
			}else{
				switch($tag[0]){
					case T_PRIVATE:
					case T_PUBLIC:
					case T_PROTECTED:
					case T_VAR:
					case T_STATIC:
						$code .= $tag[1];
						$lastspace = false;
						$lastObjectVar = true;
						break;
					case T_FUNCTION:
						$code .= $tag[1];
						$lastspace = false;
						$lastObjectVar = false;
						break;
					case T_OBJECT_OPERATOR:
						$code .= $tag[1];
						$lastObjectVar = true;
						break;
					case T_STRING:
						if($lastObjectVar === true and $obfuscate === true){
							$xorKey = Utils::getRandomBytes(strlen($tag[1]), false);
							$code .= '{"'.$this->encodeString($tag[1] ^ $xorKey).'"^"'.$this->encodeString($xorKey).'"}';
						}else{
							$code .= $tag[1];
						}
						$lastObjectVar = false;
						$lastspace = false;
						break;
					case T_VARIABLE:
						if($obfuscate === false){
							$code .= $tag[1];
							$lastspace = false;
							break;
						}
						if($lastObjectVar === true){							
							//$code .= '${"'.substr($this->encodeString($tag[1]), 1).'"}';
							$code .= $tag[1];
							$lastspace = false;
							break;
						}elseif(!isset($variables[$tag[1]])){
							$cnt = 7;
							$rangesF = range(0x41, 0x5a) + range(0x61, 0x7a) + range(0x7f, 0xff);
							$ranges = $rangesF + range(0x30, 0x39);
							while(true){
								$v = '$'.chr($rangesF[mt_rand(0, count($rangesF) - 1)]);
								for($k = $cnt; $k > 0; --$k){
									$v .= chr($ranges[mt_rand(0, count($ranges) - 1)]);
								}
								if(!in_array($v, $variables, true)){
									$variables[$tag[1]] = $v;
									break;
								}
								++$cnt;
							}
						}
						$code .= $variables[$tag[1]];
						$lastspace = false;
						$lastvar = $variables[$tag[1]];
						break;
					case T_CONSTANT_ENCAPSED_STRING:
						if($obfuscate === false){
							$code .= $tag[1];
							$lastspace = false;
							break;
						}
						$c = $tag[1]{0};
						$tag[1] = substr($tag[1], 1, -1);						
						$code .= $c. $this->encodeString($tag[1]) .$c;
						$lastspace = false;
						break;
					case T_ENCAPSED_AND_WHITESPACE:	
						if($obfuscate === false){
							$code .= $tag[1];
							$lastspace = false;
							break;
						}				
						$code .= $this->encodeString($tag[1]);
						$lastspace = false;
						break;
					case T_COMMENT:
					case T_DOC_COMMENT:
					case T_OPEN_TAG:
					case T_CLOSE_TAG:
					case T_INLINE_HTML:
					case T_BAD_CHARACTER:
						break;
					case T_LOGICAL_AND:
						$code .= "&&";
						$lastspace = false;
						break;
					case T_LOGICAL_OR:
						$code .= "||";
						$lastspace = false;
						break;
					case T_WHITESPACE:
						switch(str_replace("\t", "", $tag[1])){
							case " ":
							case "\r\n":
							case "\n":
								if($lastspace !== true){
									$code .= " ";
									$lastspace = true;
								}
								break;
						}
						break;
					case T_END_HEREDOC:
						$code .= $tag[1]."\n";
						$lastspace = false;
						break;	
					default:
						$code .= $tag[1];
						$lastspace = false;
						break;
				}
			}
		}
		$code = gzdeflate($code, 9);
		$pmf->write($code);
		$output .= "The PMF version of the plugin has been created! It has been saved on the \"".$this->api->plugin->configPath($this)."\" folder\n";
	}
	
	private function compilePM(&$output, $deflate = false){
		$fp = fopen($this->api->plugin->configPath($this)."PocketMine-MP_".MAJOR_VERSION."_[".CURRENT_MINECRAFT_VERSION."].php", "wb");
		$srcdir = realpath(FILE_PATH."src/");
		fwrite($fp, str_replace(array(
			"{{version}}",
			"{{time}}",
			"{{mcpe}}",
		), array(
			MAJOR_VERSION,
			microtime(true),
			CURRENT_MINECRAFT_VERSION,
		), DevTools::$compileHeader));
		$inc = get_included_files();
		$inc[] = array_shift($inc);
		foreach($inc as $s){
			if(strpos(realpath(dirname($s)), $srcdir) === false and strtolower(basename($s)) !== "pocketmine-mp.php"){
				continue;
			}
			$n = realpath($s);
			console("insert ".$n);
			$buff = PHP_EOL."//---- ".basename($n)." @ ".sha1_file($n)." ----".PHP_EOL;
			$drop = false;
			$lastspace = true;
			$code = token_get_all(file_get_contents($n));
			foreach($code as $index => $tag){
				if(!is_array($tag)){
					if($drop === false){
						$buff .= $tag;
					}
				}else{
					switch($tag[0]){
						case T_COMMENT:
						case T_DOC_COMMENT:
							if(strpos($tag[1], "**REM_START**") !== false){
								$drop = true;
							}elseif(strpos($tag[1], "**REM_END**") !== false){
								$drop = false;
							}
							break;
						case T_OPEN_TAG:
						case T_CLOSE_TAG:
						case T_INLINE_HTML:
						case T_BAD_CHARACTER:
							break;
						case T_WHITESPACE:
							switch(str_replace("\t", "", $tag[1])){
								case " ":
								case "\r\n":
								case "\n":
									if($drop === false and $lastspace !== true){
										$buff .= " ";
										$lastspace = true;
									}
									break;
							}
							break;
						default:
							if($drop === false){
								$buff .= $tag[1];
								$lastspace = false;
							}
							break;
					}
				}
			}
			if($deflate === false){
				fwrite($fp, $buff);
			}else{
				$deflate .= $buff;
			}
		}
		if($deflate !== false){
			$data = gzdeflate($deflate, 9);
			fwrite($fp, PHP_EOL."//DEFLATE Compressed PocketMine-MP | ".round((strlen($data)/strlen($deflate))*100, 2)."% (".round(strlen($data)/1024, 2)."KB/".round(strlen($deflate)/1024, 2)."KB)".PHP_EOL."\$fp = fopen(__FILE__, \"r\");".PHP_EOL."fseek(\$fp, __COMPILER_HALT_OFFSET__);".PHP_EOL."eval(gzinflate(stream_get_contents(\$fp)));".PHP_EOL."__halt_compiler();".$data);
		}
		fclose($fp);
	}
	
	public function __destruct(){
	}

}