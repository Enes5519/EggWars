<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use Enes5519\EggWars\task\MapResetAsyncTask;
use pocketmine\form\Form;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\level\Position;
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

	/** @var Scoreboard */
	private $scoreBoard;

	public function __construct(string $name, string $waitingLobbyName, int $teamCount, int $perTeamPlayerCount, array $spawnPoints, array $eggPositions){
		Server::getInstance()->loadLevel($name);
		Server::getInstance()->loadLevel($waitingLobbyName);

		$this->arenaLevel = Server::getInstance()->getLevelByName($name);
		$this->waitingLobby = Server::getInstance()->getLevelByName($waitingLobbyName);
		$this->teamCount = $teamCount;
		$this->perTeamPlayerCount = $perTeamPlayerCount;

		$this->teams = self::TEAMS;
		$this->teams = array_map(function(){ return 0; }, array_splice($this->teams, 0, $this->teamCount));

		$this->spawnPoints = array_combine(array_keys($this->teams), $spawnPoints);
		$this->eggPositions = array_combine(array_keys($this->teams), $eggPositions);

		$this->maxPlayers = $this->teamCount * $this->perTeamPlayerCount;

		$this->scoreBoard = new Scoreboard($this->getName(), $this->teams);
	}

	public function getName() : string{
		return $this->arenaLevel->getFolderName();
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
					if($this->time > 15){
						if(count($this->players) === $this->getMaxPlayers()){
							$this->time = 15;
						}
					}

					if($this->time === 0){
						$this->status = self::STATUS_GAME;
						$this->time = 31 * 60;

						foreach($this->players as $playerData){
							/** @var Player $player */
							$player = $playerData['player'];
							$this->resetPlayer($player);
							$player->teleport(Position::fromObject($this->spawnPoints[$playerData['team']], $this->arenaLevel));
						}
					}elseif($this->time % 15 == 0){
						$this->broadcastMessage('Oyunun başlamasına ' . TextFormat::GREEN . $this->time . TextFormat::GRAY . ' saniye kaldı.');
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
					return;
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
		$this->restarted = false;
		$this->teams = array_map(function(){ return 0; }, $this->teams);
		$this->scoreBoard->resetScores();
		$this->brokenEggs = [];
		$this->time = 60;
		$this->status = self::STATUS_LOBBY;

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

			Server::getInstance()->broadcastMessage(EggWars::PREFIX . $this->getName() . ' arenasının oyunu bitti.');
		}else{
			$team = '';
			foreach($this->players as $playerData){
				/** @var Player $player */
				$player = $playerData['player'];
				$team = $playerData['team'];
				$player->addTitle(TextFormat::GREEN . 'Kazandın');
				$this->quit($player, false);
			}

			Server::getInstance()->broadcastMessage(EggWars::PREFIX . $this->getName() . ' arenasını ' . self::TEAMS[$team][0] . $team . TextFormat::GRAY . ' takımı kazandı.');
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

		$team = key($this->getAvailableTeams());
		$this->players[$player->getLowerCaseName()] = [
			'player' => $player,
			'team' => $team,
		];
		$this->scoreBoard->setScore($team, ++$this->teams[$team]);
		$this->scoreBoard->send($player);

		$player->setDisplayName(self::TEAMS[$team][0] . $player->getName());
		$player->setNameTag($player->getDisplayName());

		$this->resetPlayer($player, false);

		$player->getInventory()->setItem(0, (ItemFactory::get(ItemIds::MAGMA_CREAM))->setCustomName(TextFormat::RESET . TextFormat::BLUE . 'Takım Değiştir'));
		$player->getInventory()->setItem(8, (ItemFactory::get(ItemIds::OAK_DOOR))->setCustomName(TextFormat::RESET . TextFormat::RED . 'Oyundan Çık'));
		$player->teleport($this->waitingLobby->getSpawnLocation());

		$this->broadcastMessage($player->getDisplayName() . TextFormat::GRAY . ' oyuna katıldı. [' . count($this->players) . '/' . $this->maxPlayers . ']');
	}

	public function quit(Player $player, bool $message = true) : void{
		$team = $this->getTeam($player);

		$this->scoreBoard->remove($player);
		$this->scoreBoard->setScore($team, --$this->teams[$team]);

		unset($this->players[$player->getLowerCaseName()]);

		if($message) $this->broadcastMessage($player->getDisplayName() . TextFormat::GRAY . ' oyundan ayrıldı.');

		$this->resetPlayer($player);
		$player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
	}

	public function teleportToBase(Player $player) : void{
		$player->teleport($this->spawnPoints[$this->getTeam($player)]);
		$this->resetPlayer($player, false);
	}

	public function isBrokenEgg(Player $player) : bool{
		return isset($this->brokenEggs[$this->getTeam($player)]);
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
					$this->brokenEggs[$team] = true;
					$this->broadcastMessage($player->getName() . ', ' . self::TEAMS[$team][0] . $team . TextFormat::GRAY . ' takımın yumurtasını kırdı.');
					break;
				}else{
					$player->sendMessage(EggWars::PREFIX . 'Kendi yumurtanı kıramazsın.');
					return true;
				}
			}
		}

		return false;
	}

	public function changeTeam(Player $player, string $newTeam) : void{
		$availableTeams = $this->getAvailableTeams();
		if(!isset($availableTeams[$newTeam])){
			$player->sendMessage(EggWars::PREFIX . 'Bu takım müsait değil.');
			return;
		}

		$team = $this->getTeam($player);
		if($team === $newTeam){
			$player->sendMessage(EggWars::PREFIX . 'Zaten bu takımdasın');
			return;
		}

		$this->scoreBoard->setScore($team, --$this->teams[$team], false);
		$this->players[$player->getLowerCaseName()]['team'] = $newTeam;
		$this->scoreBoard->setScore($team, ++$this->teams[$newTeam]);

		$player->sendMessage(EggWars::PREFIX . 'Takımınız ' . self::TEAMS[$newTeam][0] . $newTeam . TextFormat::GRAY . ' olarak değiştirildi.');
	}

	public function getTeam(Player $player) : string{
		return $this->players[$player->getLowerCaseName()]['team'] ?? 'Bilinmeyen';
	}

	public function resetPlayer(Player $player, bool $nameTag = true) : void{
		$player->setFood($player->getMaxFood());
		$player->setHealth($player->getMaxHealth());
		$player->getInventory()->clearAll();

		if($nameTag){
			$player->setNameTag($player->getName());
			$player->setDisplayName($player->getName());
		}
	}

	public function broadcastMessage(string $message) : void{
		foreach($this->players as $data){
			/** @var Player $player */
			$player = $data['player'];
			$player->sendMessage(EggWars::PREFIX . $message);
		}
	}

	public function chat(string $message, string $team = null) : void{
		foreach($this->players as $data){
			/** @var Player $player */
			$player = $data['player'];

			if($team === null or $data['team'] === $team){
				$player->sendMessage($message);
			}
		}
	}

	public function getAvailableTeams() : array{
		$availableTeam = array_filter($this->teams, function(string $number) : bool{
			return $number < $this->perTeamPlayerCount;
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