<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class SetupArena{

	/** @var string */
	public $name, $waitingLobby;
	/** @var int */
	public $teamCount, $perTeamPlayerCount;
	/** @var array */
	public $spawnPoints = [];
	/** @var array */
	public $eggPositions = [];

	public function __construct(string $name, string $waitingLobby, int $teamCount, int $perTeamPlayerCount){
		$this->name = $name;
		$this->waitingLobby = $waitingLobby;
		$this->teamCount = $teamCount;
		$this->perTeamPlayerCount = $perTeamPlayerCount;
	}

	public function setPosition(Player $player, Vector3 $pos) : bool{
		$pos = $pos->add(0, 1, 0);

		if(count($this->eggPositions) < $this->teamCount){
			$this->eggPositions[] = $pos;
			$player->sendMessage(EggWars::PREFIX . TextFormat::GREEN . count($this->eggPositions) . '. yumurta ayarlandı.');
			if(count($this->eggPositions) === $this->teamCount){
				$player->sendMessage(EggWars::PREFIX . 'Şimdi doğum noktalarını ayarlaman gerekiyor.');
			}else{
				$player->sendMessage(EggWars::PREFIX . 'Diğer yumurtaya dokun.');
			}
		}elseif(count($this->spawnPoints) < $this->teamCount){
			$this->spawnPoints[] = $pos;
			$player->sendMessage(EggWars::PREFIX . TextFormat::GREEN . count($this->spawnPoints) . '. doğum noktası ayarlandı.');
			if(count($this->spawnPoints) === $this->teamCount){
				$player->sendMessage(EggWars::PREFIX . 'Kurulum bitti');
				return true;
			}else{
				$player->sendMessage(EggWars::PREFIX . 'Diğer doğum noktasına dokun.');
			}
		}

		return false;
	}

}