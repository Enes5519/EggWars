<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\form\Form;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Arena{
	public const TEAMS = [
		'Kırmızı' => [TextFormat::RED, 'textures/blocks/wool_colored_red'],
		'Mavi' => [TextFormat::BLUE, 'textures/blocks/wool_colored_blue'],
		'Sarı' => [TextFormat::YELLOW, 'textures/blocks/wool_colored_yellow'],
		'Yeşil' => [TextFormat::GREEN, 'textures/blocks/wool_colored_green'],
		'Turuncu' => [TextFormat::GOLD, 'textures/blocks/wool_colored_orange'],
		'Açık Mavi' => [TextFormat::AQUA, 'textures/blocks/wool_colored_light_blue'],
		'Mor' => [TextFormat::LIGHT_PURPLE, 'textures/blocks/wool_colored_purple'],
		'Siyah' => [TextFormat::BLACK, 'textures/blocks/wool_colored_black']
	];

	public const STATUS_TEXT = [
		self::STATUS_LOBBY => TextFormat::GREEN . 'Lobi',
		self::STATUS_GAME => TextFormat::GOLD . 'Oyunda',
		self::STATUS_RESTART => TextFormat::RED . 'Yenileniyor'
	];

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

	/** @var int */
	private $status = self::STATUS_LOBBY;
	/** @var bool */
	private $restarted = false;

	/** @var int */
	private $maxPlayers;
	/** @var array */
	private $teams;
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

		$this->teams = self::TEAMS;
		array_splice($this->teams, 0, $this->teamCount);
		$this->teams = array_map(function(){ return 0; }, array_flip($this->teams));

		$this->spawnPoints = array_combine(array_keys($this->teams), $spawnPoints);
		$this->eggPositions = array_combine(array_keys($this->teams), $eggPositions);

		$this->maxPlayers = $this->teamCount * $this->perTeamPlayerCount;
	}

	/**
	 * @return array
	 */
	public function getPlayers() : array{
		return $this->players;
	}

	public function getMaxPlayers() : int{
		return $this->maxPlayers;
	}

	/**
	 * @return int
	 */
	public function getStatus() : int{
		return $this->status;
	}

	public function tick() : void{
		switch($this->status){
			case self::STATUS_LOBBY:
				$minPlayer = $this->teamCount;
				if(count($this->players) >= $minPlayer){
					if($this->time === 0){
						$this->status = self::STATUS_GAME;
						$this->time = 31 * 60;

						foreach($this->players as $playerData){
							/** @var Player $player */
							$player = $playerData['player'];
							$this->resetPlayer($player);
							$player->teleport($this->spawnPoints[$playerData['team']]);
						}
					}elseif($this->time % 15 == 0){
						$this->broadcastMessage('Oyunun başlamasına ' . TextFormat::GREEN . $this->time . TextFormat::DARK_GRAY . ' saniye kaldı.');
					}

					--$this->time;
				}else{
					$this->time = 60;
				}
				break;
			case self::STATUS_GAME:
				if($this->time === 0){
					$this->finish(null);
				}elseif($this->time % 60 == 0){
					$this->broadcastMessage('Oyunun bitmesine ' . ($this->time / 60) . ' dakika kaldı.');
				}

				$getTeams = [];
				foreach($this->teams as $team => $count){
					if($count >= 1){
						$getTeams[] = $team;
					}
				}

				if(count($getTeams) <= 1){
					$this->finish(array_shift($getTeams));
				}

				--$this->time;
				break;
			case self::STATUS_RESTART:
				$this->restart();
				break;
		}
	}

	public function restart() : void{
		if(!$this->restarted){
			Server::getInstance()->getAsyncPool()->submitTask(new MapResetAsyncTask($this->arenaLevel));
		}
	}

	public function restartCompleted() : void{
		$this->status = self::STATUS_LOBBY;
		$this->restarted = false;
		$this->teams = array_map(function(){ return 0; }, array_flip($this->teams));
		$this->time = 60;

		foreach($this->waitingLobby->getEntities() as $entity){
			$entity->flagForDespawn();
		}
	}

	public function finish(?string $team = null) : void{
		$this->status = self::STATUS_RESTART;

		if($team === null){
			foreach($this->players as $playerData){
				/** @var Player $player */
				$player = $playerData['player'];
				$player->sendMessage(EggWars::PREFIX . 'Oyun bitti. Kazanan yok.');
				$this->quit($player, false);
			}
		}else{
			foreach($this->players as $playerData){
				/** @var Player $player */
				$player = $playerData['player'];
				$player->sendMessage(EggWars::PREFIX . TextFormat::GREEN . 'KAZANDIN!');
				$this->quit($player, false);
			}
		}
	}

	public function join(Player $player) : void{
		if($this->status !== self::STATUS_LOBBY){
			$player->sendMessage(EggWars::PREFIX . 'Oyun çoktan başlamış...');
			return;
		}

		if($this->players === $this->maxPlayers){
			$player->sendMessage(EggWars::PREFIX . 'Oyun dolu');
			return;
		}

		if($this->inArena($player)){
			$player->sendMessage(EggWars::PREFIX . 'Zaten oyundasınız.');
			return;
		}

		$team = array_shift($this->getAvailableTeams());
		$this->players[$player->getLowerCaseName()] = [
			'player' => $player,
			'team' => $team,
		];
		++$this->teams[$team];

		$this->resetPlayer($player);

		$player->getInventory()->setItem(0, (ItemFactory::get(ItemIds::MAGMA_CREAM))->setCustomName(TextFormat::RESET . TextFormat::BLUE . 'Takım Değiştir'));
		$player->getInventory()->setItem(8, (ItemFactory::get(ItemIds::OAK_DOOR))->setCustomName(TextFormat::RESET . TextFormat::RED . 'Oyundan Çık'));
		$player->teleport($this->waitingLobby->getSpawnLocation());

		$this->broadcastMessage(TextFormat::YELLOW . $player->getName() . TextFormat::GRAY . ' oyuna katıldı. [' . count($this->players) . '/' . $this->maxPlayers . ']');
	}

	public function quit(Player $player, bool $message = true) : void{
		--$this->teams[$this->players[$player->getLowerCaseName()]];
		unset($this->players[$player->getLowerCaseName()]);

		$this->resetPlayer($player);

		$player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
		if($message) $this->broadcastMessage(TextFormat::RED . $player->getName() . TextFormat::GRAY . ' oyundan ayrıldı.');
	}

	public function inArena(Player $player) : bool{
		return isset($this->players[$player->getLowerCaseName()]);
	}

	public function breakEgg(Player $player, Vector3 $pos) : bool{
		/**
		 * @var string $team
		 * @var Vector3 $position
		 */
		foreach($this->eggPositions as $team => $position){
			if($position->equals($pos)){
				if($team !== $this->getTeam($player)){
					/// TODO
					break;
				}else{
					$player->sendMessage(EggWars::PREFIX . 'Kendi yumurtanı kıramazsın.');
					return true;
				}
			}
		}

		return false;
	}

	public function changeTeam(Player $player, string $team) : void{
		if(isset($this->getAvailableTeams()[$team])){
			$player->sendMessage(EggWars::PREFIX . 'Bu takım müsait değil.');
			return;
		}

		--$this->teams[$this->getTeam($player)];
		$this->players[$player->getLowerCaseName()]['team'] = $team;
		++$this->teams[$teamName = $this->getTeam($player)];

		$player->sendMessage(EggWars::PREFIX . 'Takımınız ' . self::TEAMS[$teamName][0] . $teamName . TextFormat::GRAY . ' olarak değiştirildi.');
	}

	public function getTeam(Player $player) : string{
		return $this->players[$player->getLowerCaseName()]['team'] ?? 'Bilinmeyen';
	}

	public function resetPlayer(Player $player) : void{
		$player->setFood($player->getMaxFood());
		$player->setHealth($player->getMaxHealth());
		$player->getInventory()->clearAll();
	}

	public function broadcastMessage(string $message) : void{
		foreach($this->players as $data){
			/** @var Player $player */
			$player = $data['player'];
			$player->sendMessage(EggWars::PREFIX . $message);
		}
	}

	public function getAvailableTeams() : array{
		$availableTeam = array_filter($this->teams, function(string $number) : bool{
			return $number < $this->teamCount;
		});
		asort($availableTeam);

		return $availableTeam;
	}

	public function changeTeamForm() : Form{
		$options = [];
		foreach($this->teams as $team => $teamCount){
			$options[] = new MenuOption(self::TEAMS[$team][0] . $team . TextFormat::EOL . TextFormat::RESET . '[' . $teamCount . '/' . $this->perTeamPlayerCount . ']');
		}
		return new MenuForm(TextFormat::BLUE . 'Takım Değiştir', '', $options, function(Player $player, int $selectedOption) : void{
			if($this->getStatus() !== Arena::STATUS_LOBBY){
				$player->sendMessage(EggWars::PREFIX . 'Takım değiştirmek için çok geç!');
			}else{
				$this->changeTeam($player, array_keys($this->teams)[$selectedOption]);
			}
		});
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