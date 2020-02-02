<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use pocketmine\level\Level;
use pocketmine\Server;

class Arena{
	public const TEAMS = ['Kırmızı', 'Mavi', 'Sarı', 'Yeşil', 'Turuncu', 'Açık Mavi', 'Siyah', 'Beyaz'];

	public const CONFIG_WAITING_LOBBY = 'waitingLobbyLevel';
	public const CONFIG_TEAM_COUNT = 'teamCount';
	public const CONFIG_PER_TEAM_PLAYER = 'perTeamPlayerCount';
	public const CONFIG_SPAWN_POINTS = 'spawnPoints';
	public const CONFIG_EGG_POSITIONS = 'eggPositions';

	/** @var Level */
	private $arenaLevel, $waitingLobby;
	/** @var int */
	private $teamCount, $perTeamPlayerCount;
	/** @var array */
	private $spawnPoints = [];
	/** @var array */
	private $eggPositions = [];


	public const STATUS_LOBBY = 0;
	public const STATUS_GAME = 1;
	public const STATUS_RESTART = 2;

	private $mode = self::STATUS_LOBBY;
	/** @var int */
	private $time = 60;
	/** @var array */
	private $players = [];
	/** @var array */
	private $brokenEggs = [];

	public function __construct(string $name, string $waitingLobbyName, int $teamCount, int $perTeamPlayerCount, array $spawnPoints, array $eggPositions){
		Server::getInstance()->loadLevel($name);
		Server::getInstance()->loadLevel($waitingLobbyName);

		$this->arenaLevel = Server::getInstance()->getLevelByName($name);
		$this->waitingLobby = Server::getInstance()->getLevelByName($waitingLobbyName);
		$this->teamCount = $teamCount;
		$this->perTeamPlayerCount = $perTeamPlayerCount;
		$this->spawnPoints = $spawnPoints;
		$this->eggPositions = $eggPositions;
	}

	public function tick() : void{
		switch($this->mode){
			case self::STATUS_LOBBY:
				$minPlayer = $this->teamCount;
				if(count($this->players) >= $minPlayer){
					if($this->time === 0){
						// TODO : Start
					}elseif($this->time % 15 == 0){
						// TODO : Broadcast messag
					}
					$this->time--;
				}else{
					$this->time = 60;
				}
				break;
		}
	}

	public static function fromArray(string $arenaName, array $data) : Arena{
		return new Arena(
			$arenaName,
			$data[self::CONFIG_WAITING_LOBBY],
			$data[self::CONFIG_TEAM_COUNT],
			$data[self::CONFIG_PER_TEAM_PLAYER],
			array_map([EggWars::class, 'decodeVector3'], $data[self::CONFIG_SPAWN_POINTS]),
			array_map([EggWars::class, 'decodeVector3'], $data[self::CONFIG_EGG_POSITIONS])
		);
	}

}