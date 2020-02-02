<?php

declare(strict_types=1);

namespace Enes5519\EggWars;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

class EventListener implements Listener{

	/** @var EggWars */
	private $api;

	public function __construct(EggWars $api){
		$this->api = $api;
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @ignoreCancelled true
	 */
	public function onInteract(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();

		if($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK and isset(EggWars::$setup[$player->getLowerCaseName()])){
			$setup = EggWars::$setup[$player->getLowerCaseName()];
			if($setup->setPosition($player, $event->getBlock()->asVector3())){
				$this->api->zipWorld($setup->name);

				file_put_contents($this->api->getDataFolder() . 'arenas' . DIRECTORY_SEPARATOR . $setup->name . '.yml', yaml_emit([
					Arena::CONFIG_TEAM_COUNT => $setup->teamCount,
					Arena::CONFIG_PER_TEAM_PLAYER => $setup->perTeamPlayerCount,
					Arena::CONFIG_EGG_POSITIONS => array_map([EggWars::class, 'hashVector3'], $setup->eggPositions),
					Arena::CONFIG_SPAWN_POINTS => array_map([EggWars::class, 'hashVector3'], $setup->spawnPoints)
				], YAML_UTF8_ENCODING));

				EggWars::$arenas[$setup->name] = new Arena($setup->name, $setup->waitingLobby, $setup->teamCount, $setup->perTeamPlayerCount, $setup->spawnPoints, $setup->eggPositions);

				unset(EggWars::$setup[$player->getLowerCaseName()]);

				$player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
				$player->sendMessage(EggWars::PREFIX . 'Arena kuruldu');
			}
		}
	}

}