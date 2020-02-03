<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use Enes5519\EggWars\command\EggWarsCommand;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class EggWars extends PluginBase{
	public const PREFIX = TextFormat::GOLD . 'EggWars' . TextFormat::DARK_GRAY . '> ' . TextFormat::GRAY;
	public const SIGN_PREFIX = TextFormat::GRAY . '[' . TextFormat::GOLD . 'EggWars' . TextFormat::GRAY . ']';

	/** @var Arena[] */
	public static $arenas = [];
	/** @var SetupArena[] */
	public static $setup;

	/** @var EggWars */
	private static $api;

	public function onEnable(){
		EggWars::$api = $this;

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
		$this->getScheduler()->scheduleRepeatingTask(new class() extends Task{
			public function onRun(int $currentTick){
				foreach(Server::getInstance()->getDefaultLevel()->getTiles() as $tile){
					/** @var Sign $tile */
					if($tile->getId() === Tile::SIGN){
						if($tile->getLine(0) === EggWars::SIGN_PREFIX){
							$arena = EggWars::$arenas[$tile->getLine(1)] ?? null;
							if($arena !== null){
								$tile->setText(null, null, TextFormat::WHITE . count($arena->getPlayers()) . '/' . $arena->getMaxPlayers(), Arena::STATUS_TEXT[$arena->getStatus()]);
							}
						}
					}
				}
			}
		}, 20);
	}

	/**
	 * @return EggWars
	 */
	public static function getAPI() : EggWars{
		return self::$api;
	}

	public static function getZipWorldPath() : string{
		return self::$api->getDataFolder() . 'worlds' . DIRECTORY_SEPARATOR;
	}

	public function getPlayerArena(Player $player) : ?Arena{
		foreach(self::$arenas as $arena){
			if($arena->inArena($player)){
				return $arena;
			}
		}

		return null;
	}

	public function zipWorld(string $worldName) : void{
		$worldsPath = $this->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR;
		$targetPath = self::getZipWorldPath();

		$zip = new ZipArchive();
		$zip->open($targetPath . $worldName . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath($worldsPath . DIRECTORY_SEPARATOR . $worldName)), RecursiveIteratorIterator::LEAVES_ONLY);

		/** @var SplFileInfo $file */
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