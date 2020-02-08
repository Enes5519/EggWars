<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Scoreboard{
	private const TITLE = TextFormat::GOLD . 'EggWars';

	/** @var string */
	private $objectiveName;
	/** @var int */
	private $scoreboardId;
	/** @var ScorePacketEntry[] */
	private $entries;
	/** @var Player[] */
	private $viewers = [];

	public function __construct(string $arenaName, array $teams){
		$this->objectiveName = $arenaName;
		$this->scoreboardId = Entity::$entityCount++;
		$line = 0;
		foreach($teams as $team => $_){
			$entry = new ScorePacketEntry();
			$entry->objectiveName = $this->objectiveName;
			$entry->scoreboardId = $this->scoreboardId + (++$line);
			$entry->score = 0;

			$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
			$entry->customName = Arena::TEAMS[$team][0] . '■ ' . TextFormat::GRAY . $team;

			$this->entries[$team] = $entry;
		}
	}

	public function resetScores() : void{
		foreach($this->entries as $team => $_){
			$this->entries[$team]->score = 0;
		}
	}

	public function setScore(string $team, int $score, bool $send = true) : void{
		$this->entries[$team]->score = $score;

		if($send){
			$pk = new SetScorePacket();
			$pk->type = SetScorePacket::TYPE_CHANGE;
			$pk->entries = $this->entries;
			Server::getInstance()->broadcastPacket($this->viewers, $pk);
		}
	}

	public function send(Player $player) : void{
		$pk = new SetDisplayObjectivePacket();
		$pk->objectiveName = $this->objectiveName;
		$pk->displaySlot = 'sidebar';
		$pk->displayName = self::TITLE;
		$pk->criteriaName = 'dummy';
		$pk->sortOrder = 0;
		$player->sendDataPacket($pk);
		$this->viewers[$player->getLowerCaseName()] = $player;

		$this->sendLines($player);
	}

	public function sendLines(Player $player) : void{
		$pk = new SetScorePacket();
		$pk->type = SetScorePacket::TYPE_CHANGE;
		$pk->entries = $this->entries;
		$player->sendDataPacket($pk);
	}

	public function remove(Player $player) : void{
		unset($this->viewers[$player->getLowerCaseName()]);

		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $this->objectiveName;
		$player->sendDataPacket($pk);
	}
}