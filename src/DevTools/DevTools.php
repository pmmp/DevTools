<?php

namespace DevTools;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\protocol\Info;
use pocketmine\plugin\FolderPluginLoader;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class DevTools extends PluginBase{
	public function onEnable(){
		@mkdir($this->getDataFolder());
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "makeplugin":
				return $this->makePluginCommand($sender, $command, $label, $args);
				break;
			case "makeserver":
				return $this->makeServerCommand($sender, $command, $label, $args);
				break;
			default:
				return false;
		}
	}

	private function makePluginCommand(CommandSender $sender, Command $command, $label, array $args){
		$pluginName = trim(implode(" ", $args));
		if($pluginName === "" or !(($plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName)) instanceof Plugin)){
			$sender->sendMessage(TextFormat::RED . "Invalid plugin name, check the name case.");
			return true;
		}
		$description = $plugin->getDescription();

		if(!($plugin->getPluginLoader() instanceof FolderPluginLoader)){
			$sender->sendMessage(TextFormat::RED . "Plugin ".$description->getName()." is not in folder structure.");
			return true;
		}

		$pharPath = $this->getDataFolder() . DIRECTORY_SEPARATOR . $description->getName()."_v".$description->getVersion().".phar";
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
			"website" => $description->getWebsite()
		]);
		$phar->setStub('<?php echo "PocketMine-MP plugin '.$description->getName() .' v'.$description->getVersion().'\nThis file has been generated using DevTools v'.$this->getDescription()->getVersion().' at '.date("r").'\n----------------\n";if(extension_loaded("phar")){$phar = new \Phar(__FILE__);foreach($phar->getMetadata() as $key => $value){echo ucfirst($key).": ".(is_array($value) ? implode(", ", $value):$value)."\n";}} __HALT_COMPILER();');
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$reflection = new \ReflectionClass("pocketmine\\plugin\\PluginBase");
		$file = $reflection->getProperty("file");
		$file->setAccessible(true);
		$filePath = realpath($file->getValue($plugin));
		$phar->startBuffering();
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath)) as $file){
			$path = ltrim(str_replace(array($filePath, "\\"), array("", "/"), $file), "/");
			if($path{0} === "." or strpos($path, "/.") !== false){
				continue;
			}
			$phar->addFile($file, $path);
		}

		$phar->compressFiles(\Phar::GZ);
		$phar->stopBuffering();
		$sender->sendMessage("Phar plugin ".$description->getName() ." v".$description->getVersion()." has been created on ".$pharPath);
		return true;
	}

	private function makeServerCommand(CommandSender $sender, Command $command, $label, array $args){
		$server = Server::getInstance();
		$pharPath = $this->getDataFolder() . DIRECTORY_SEPARATOR . $server->getName().".phar";
		if(file_exists($pharPath)){
			$sender->sendMessage("Phar file already exists, overwriting...");
			@unlink($pharPath);
		}
		$phar = new \Phar($pharPath);
		$phar->setMetadata([
			"name" => $server->getName(),
			"version" => $server->getPocketMineVersion(),
			"api" => $server->getApiVersion(),
			"minecraft" => $server->getVersion(),
			"protocol" => Info::CURRENT_PROTOCOL
		]);
		$phar->setStub('<?php define("pocketmine\\\\PATH", "phar://". __FILE__ ."/"); require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php");  __HALT_COMPILER();');
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->startBuffering();

		$filePath = realpath(\pocketmine\PATH);
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath . "/src")) as $file){
			$path = ltrim(str_replace(array($filePath, "\\"), array("", "/"), $file), "/");
			if($path{0} === "." or strpos($path, "/.") !== false or substr($path, 0, 4) !== "src/"){
				continue;
			}
			echo $path,PHP_EOL;
			$phar->addFile($file, $path);
		}
		$phar->compressFiles(\Phar::GZ);
		$phar->stopBuffering();

		$sender->sendMessage("PocketMine-MP Phar file has been created on ".$pharPath);

		return true;
	}

}