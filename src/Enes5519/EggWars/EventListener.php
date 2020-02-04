<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;

class EventListener implements Listener{

	/** @var EggWars */
	private $api;

	public function __construct(EggWars $api){
		$this->api = $api;
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();

		if(($arena = $this->api->getPlayerArena($player)) !== null){
			$arena->quit($player);
		}
	}

	public function onSignChange(SignChangeEvent $event) : void{
		if($event->getPlayer()->hasPermission('enes5519.eggwars.op')){
			if($event->getLine(0) === 'eggwars'){
				$arenaName = $event->getLine(1);
				if(isset(EggWars::$arenas[$arenaName])){
					$event->setLines([
						EggWars::SIGN_PREFIX,
						$arenaName,
						'Yükleniyor',
						''
					]);
				}
			}
		}
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @ignoreCancelled true
	 */
	public function onInteract(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();

		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			if(isset(EggWars::$setup[$player->getLowerCaseName()])){
				$setup = EggWars::$setup[$player->getLowerCaseName()];
				if($setup->setPosition($player, $event->getBlock()->asVector3())){
					$this->api->zipWorld($setup->name);

					file_put_contents($this->api->getDataFolder() . 'arenas' . DIRECTORY_SEPARATOR . $setup->name . '.yml', yaml_emit([
						Arena::CONFIG_TEAM_COUNT => $setup->teamCount,
						Arena::CONFIG_PER_TEAM_PLAYER => $setup->perTeamPlayerCount,
						Arena::CONFIG_EGG_POSITIONS => array_map([EggWars::class, 'hashVector3'], $setup->eggPositions),
						Arena::CONFIG_SPAWN_POINTS => array_map([EggWars::class, 'hashVector3'], $setup->spawnPoints),
						Arena::CONFIG_WAITING_LOBBY => $setup->waitingLobby
					], YAML_UTF8_ENCODING));

					EggWars::$arenas[$setup->name] = new Arena($setup->name, $setup->waitingLobby, $setup->teamCount, $setup->perTeamPlayerCount, $setup->spawnPoints, $setup->eggPositions);

					unset(EggWars::$setup[$player->getLowerCaseName()]);

					$player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
					$player->sendMessage(EggWars::PREFIX . 'Arena kuruldu');
				}
				return;
			}else{
				$block = $event->getBlock();
				if($block->getId() === ItemIds::SIGN_POST or $block->getId() === ItemIds::WALL_SIGN){
					$tile = $block->level->getTileAt($block->x, $block->y, $block->z);
					if($tile instanceof Sign){
						if($tile->getLine(0) === EggWars::SIGN_PREFIX){
							$arena = EggWars::$arenas[$tile->getLine(1)] ?? null;
							if($arena !== null){
								$arena->join($player);
								return;
							}
						}
					}
				}
			}
		}

		$item = $event->getItem();
		if($item->getId() === ItemIds::MAGMA_CREAM or $item->getId() === ItemIds::OAK_DOOR){
			$arena = $this->api->getPlayerArena($player);
			if($arena !== null and $arena->getStatus() === Arena::STATUS_LOBBY){
				if($item->getId() === ItemIds::MAGMA_CREAM){
					$player->sendForm($arena->changeTeamForm());
				}else{
					$arena->quit($player);
				}
			}
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 * @ignoreCancelled true
	 */
	public function onBreak(BlockBreakEvent $event) : void{
		$block = $event->getBlock();
		$player = $event->getPlayer();

		if($player->getLevel()->getId() !== $player->getServer()->getDefaultLevel()->getId()){
			$arena = $this->api->getPlayerArena($player);
			if($arena !== null){
				if($arena->getStatus() === Arena::STATUS_GAME){
					if($block->getId() === ItemIds::DRAGON_EGG){
						$player = $event->getPlayer();
						if($arena->getStatus() === Arena::STATUS_GAME){
							$event->setCancelled($arena->breakEgg($player, $block));
						}
					}
				}else{
					$event->setCancelled();
				}
			}
		}
	}

	public function onDrop(PlayerDropItemEvent $event) : void{
		$player = $event->getPlayer();

		if($player->getLevel()->getId() !== $player->getServer()->getDefaultLevel()->getId()){
			$arena = $this->api->getPlayerArena($player);
			if($arena !== null){
				$event->setCancelled($arena->getStatus() !== Arena::STATUS_GAME);
			}
		}
	}

	/**
	 * @param EntityDamageEvent $event
	 * @ignoreCancelled true
	 */
	public function onAttack(EntityDamageEvent $event) : void{
		$player = $event->getEntity();

		if($player instanceof Player and ($arena = $this->api->getPlayerArena($player)) !== null){
			$dead = $event->getFinalDamage() >= $player->getHealth();
			if($event instanceof EntityDamageByEntityEvent){
				$damager = $event->getDamager();
				if($damager instanceof Player){
					if($arena->getTeam($damager) !== $arena->getTeam($player)){
						if($dead){
							if($arena->isBrokenEgg($player)){
								$arena->quit($player, false);
								$player->addTitle(TextFormat::RED . 'ELENDİN!');
							}else{
								$arena->teleportToBase($player);
							}
							$event->setCancelled();
							$arena->broadcastMessage($player->getDisplayName() . TextFormat::GRAY . ', ' . $damager->getDisplayName() . TextFormat::GRAY . ' tarafından öldürüldü.');
						}
					}else{
						$event->setCancelled();
					}
				}
			}else{
				if($dead){
					if($arena->isBrokenEgg($player)){
						$arena->quit($player, false);
						$player->addTitle(TextFormat::RED . 'ELENDİN!');
					}else{
						$event->setCancelled();
						$arena->teleportToBase($player);
					}
					$arena->broadcastMessage($player->getDisplayName() . ' öldü.');
				}
			}
		}
	}
}