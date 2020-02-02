<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use Enes5519\EggWars\command\EggWarsCommand;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class EggWars extends PluginBase{
	public const PREFIX = TextFormat::GOLD . 'EggWars' . TextFormat::DARK_GRAY . '> ' . TextFormat::GRAY;

	public const SETUP_MODE_SETUP_EGG = 1;
	public const SETUP_MODE_SETUP_SPAWN_POINTS = 2;

	/** @var Arena[] */
	public static $arenas = [];
	/** @var SetupArena[] */
	public static $setup;

	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}

		if(!file_exists($this->getDataFolder() . 'arenas')){
			mkdir($this->getDataFolder() . 'arenas');
		}

		if(!file_exists($this->getDataFolder() . 'worlds')){
			mkdir($this->getDataFolder() . 'worlds');
		}

		foreach(glob($this->getDataFolder() . 'arenas' . DIRECTORY_SEPARATOR . '*.yml') as $arenaPath){
			$arenaName = basename($arenaPath, '.yml');
			if(file_exists($this->getDataFolder() . 'worlds' . DIRECTORY_SEPARATOR . $arenaName . '.zip')){
				self::$arenas[$arenaName] = Arena::fromArray($arenaName, yaml_parse($arenaPath));
			}
		}

		$this->getServer()->getCommandMap()->register('eggwars', new EggWarsCommand());

		$this->getScheduler()->scheduleRepeatingTask(new class() extends Task{
			public function onRun(int $currentTick){
				foreach(EggWars::$arenas as $arena){
					$arena->tick();
				}
			}
		}, 20);
	}

	public function zipWorld(string $worldName) : void{
		$worldsPath = $this->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR;
		$targetPath = $this->getDataFolder() . 'worlds' . DIRECTORY_SEPARATOR;

		$zip = new \ZipArchive();
		$zip->open($targetPath . $worldName . '.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(realpath($worldsPath . DIRECTORY_SEPARATOR . $worldName)), \RecursiveIteratorIterator::LEAVES_ONLY);

		/** @var \SplFileInfo $file */
		foreach($files as $file){
			if($file->isFile()) {
				$filePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename();
				$localPath = substr($filePath, strlen($worldsPath));
				$zip->addFile($filePath, $localPath);
			}
		}

		$zip->close();
	}

	public static function hashVector3(Vector3 $pos) : string{
		return $pos->x . ':' . $pos->y . ':' . $pos->z;
	}

	public static function decodeVector3(string $hash) : Vector3{
		return new Vector3(...explode(':', $hash));
	}
}