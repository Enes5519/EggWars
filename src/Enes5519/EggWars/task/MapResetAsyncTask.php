<?php

declare(strict_types=1);

namespace Enes5519\EggWars\task;

use Enes5519\EggWars\EggWars;
use pocketmine\level\Level;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class MapResetAsyncTask extends AsyncTask{

	private $levelName;
	/** @var string */
	private $worldsPath;
	/** @var string */
	private $zipPath;

	public function __construct(Level $level){
		Server::getInstance()->unloadLevel($level);

		$this->levelName = $level->getFolderName();
		$level->getServer()->unloadLevel($level);
		$this->zipPath = EggWars::getZipWorldPath() . $this->levelName . '.zip';
		$this->worldsPath = $level->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR;
	}

	/**
	 * @inheritDoc
	 */
	public function onRun(){
		$it = new RecursiveDirectoryIterator($this->worldsPath . $this->levelName, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if($file->isDir()){
				rmdir($file->getRealPath());
			}else{
				unlink($file->getRealPath());
			}
		}

		$zip = new ZipArchive();
		$zip->open($this->zipPath);
		$zip->extractTo($this->worldsPath);
		$zip->close();
	}

	public function onCompletion(Server $server){
		$server->loadLevel($this->levelName);
		EggWars::$arenas[$this->levelName]->restartCompleted();
	}
}