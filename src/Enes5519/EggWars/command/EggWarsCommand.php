<?php

declare(strict_types=1);

namespace Enes5519\EggWars\command;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Slider;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use Enes5519\EggWars\Arena;
use Enes5519\EggWars\EggWars;
use Enes5519\EggWars\SetupArena;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class EggWarsCommand extends Command{

	public function __construct(){
		parent::__construct('eggwars', 'Eggwars yönetim komutu', null, ['ew']);
		$this->setPermission('enes5519.eggwars.op');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if($sender->hasPermission($this->getPermission())){
			if($sender instanceof Player){
				$sender->sendForm(new MenuForm('EggWars - Yönetim', '', [new MenuOption('Arena Oluştur'), new MenuOption('Arena Sil')], function(Player $player, int $selectedOption) : void{
					if($selectedOption === 0){ // Arena oluştur
						$elements = [
							new Input('worldName', 'Arena Dünyasının İsmi'),
							new Input('waitingLobbyName', 'Bekleme Dünyasının İsmi'),
							new Slider('teamCount', 'Takım Sayısı', 2, count(Arena::TEAMS)),
							new Slider('perTeamPlayerCount', 'Takımdaki Oyuncu Sayısı', 1, 4)
						];
						$form = new CustomForm('Arena Oluştur', $elements, function(Player $player, CustomFormResponse $response) : void{
							$worldName = $response->getString('worldName');
							$waitingLobbyName = $response->getString('waitingLobbyLevelName');

							if(isset(EggWars::$arenas[$worldName])){
								$player->sendMessage(EggWars::PREFIX . 'Böyle bir arena var zaten.');
								return;
							}

							if(!$player->getServer()->isLevelGenerated($worldName)){
								$player->sendMessage(EggWars::PREFIX . 'Arena dünya bulunamadı.');
								return;
							}

							if(!$player->getServer()->isLevelGenerated($waitingLobbyName)){
								$player->sendMessage(EggWars::PREFIX . 'Bekleme dünyası bulunamadı.');
								return;
							}

							$teamCount = (int) $response->getFloat('teamCount');
							$perTeamPlayerCount = (int) $response->getFloat('perTeamPlayerCount');

							EggWars::$setup[$player->getLowerCaseName()] = new SetupArena($worldName, $waitingLobbyName, $teamCount, $perTeamPlayerCount);

							$player->getServer()->loadLevel($worldName);
							$player->teleport($player->getServer()->getLevelByName($worldName));
							$player->sendMessage(EggWars::PREFIX . 'Şimdi yumurtaları ayarlama zamanı!');
						});
						$player->sendForm($form);
					}else{
						/** @var MenuOption[] $options */
						$options = array_map(function(Arena $arena){
							return new MenuOption($arena->getName());
						}, EggWars::$arenas);
						$form = new MenuForm('EggWars - Arena Sil', '', $options, function(Player $player, int $selectedOption) use($options): void{
							$arena = EggWars::$arenas[$options[$selectedOption]->getText()] ?? null;
							if($arena !== null){
								$arena->delete();
								unset(EggWars::$arenas[$options[$selectedOption]->getText()]);
								$player->sendMessage(EggWars::PREFIX . 'Arena silindi!');
							}
						});
						$player->sendForm($form);
					}
				}));
			}else{
				$sender->sendMessage(EggWars::PREFIX . 'Bu komut oyuncular içindir.');
			}
		}else{
			$sender->sendMessage(EggWars::PREFIX . 'Yetkiniz yok');
		}
	}
}