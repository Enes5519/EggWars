<?php

declare(strict_types=1);

namespace Enes5519\EggWars\task;

use Enes5519\EggWars\EggWars;
use pocketmine\level\Level;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ZipArchive;

class MapResetAsyncTask extends AsyncTask{

	private $levelName;
	/** @var string */
	private $worldsPath;
	/** @var string */
	private $zipPath;

	public function __construct(Level $level){
		$this->levelName = $level->getFolderName();
		$level->getServer()->unloadLevel($level);
		$this->zipPath = EggWars::getZipWorldPath() . $this->levelName . '.zip';
		$this->worldsPath = $level->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR;
	}

	/**
	 * @inheritDoc
	 */
	public function onRun(){
		self::delDir($this->worldsPath . $this->levelName);

		$zip = new ZipArchive();
		$zip->open($this->zipPath);
		$zip->extractTo($this->worldsPath);
		$zip->close();
	}

	public static function delDir(string $path) : void{
		foreach(scandir($path, SCANDIR_SORT_NONE) as $file){
			if($file !== '.' and $file !== '..'){
				is_dir($newPath = $path . DIRECTORY_SEPARATOR . $file) ? self::delDir($newPath) : unlink($newPath);
			}
		}

		rmdir($path);
	}

	public function onCompletion(Server $server){
		$server->loadLevel($this->levelName);
		EggWars::$arenas[$this->levelName]->restartCompleted();
	}
}