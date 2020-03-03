<?php

/**
 * Copyright 2018 DragoVN
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace FFA;

use FFA\entity\EntityJoinFFA;
use FFA\task\EntityTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\item\ItemFactory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\utils\Config;
use pocketmine\item\echantment\{Enchantment, EnchantmentInstance};
use pocketmine\entity\Skin;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\{ByteTag, CompoundTag, DoubleTag, FloatTag, StringTag, ListTag, ShortTag, IntTag};

class Main extends PluginBase implements Listener{
	
	public $notpermission = "You have not permissions to use this command!";
	public $commandnotconsole = "This command can be used only in-game!";
	public $searchinggame = "Searching for empty arena...";
	private $removenpcmode = [];
    public $players = [];
	
	public function onEnable(){
		
		Entity::registerEntity(EntityJoinFFA::class, true);
		$this->getScheduler()->scheduleRepeatingTask(new EntityTag($this), 20);
		
		$this->getLogger()->info(TextFormat::GREEN . "is Enable!");
		$this->getLogger()->info(TextFormat::GREEN . "Support: pnam5005@gmail.com");
		$this->getLogger()->info(TextFormat::GREEN . "Your Version: " . $this->getDescription()->getVersion() . "\n\n");
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		$this->getResource("config.yml");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function removeKits($player){
		$player->getArmorInventory()->clearAll();
		$player->setAllowFlight(false);
		$player->setGamemode($this->getServer()->getDefaultGamemode());
		$player->getInventory()->clearAll();
	}
	
	public function onDamageEntityJoin(EntityDamageEvent $event) {
        $entity = $event->getEntity();
        if($event instanceof EntityDamageByEntityEvent) {
           $damager = $event->getDamager();
		    if(!$entity instanceof EntityJoinFFA) return;
            if($entity instanceof EntityJoinFFA && $damager instanceof Player) {
				if(isset($this->removenpcmode[$damager->getName()])) {
                    switch ($this->removenpcmode[$damager->getName()]) {
                        case 0:
						    $damager->sendMessage(TextFormat::GREEN . "Entity removed.");
							unset($this->removenpcmode[$damager->getName()]);
						    $entity->close();
							$event->setCancelled(true);
						break;
					}
					return;
				}
                $event->setCancelled(true);
                $damager->sendMessage(TextFormat::GREEN . $this->searchinggame);
                $this->onJoinGameFFA($damager);
			}
		}
	}	
	
	public function onLevelChange(EntityLevelChangeEvent $event) {
        $player = $event->getEntity();
        if(!$player instanceof Player) return;
        if($player->getLevel()->getFolderName() == $this->getConfig()->get("arena")) {
            unset($this->players[$player->getName()]);
		}
	}
	
	public function onDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
		if($player->getLevel()->getFolderName() == $this->getConfig()->get("arena")) {
		    $this->removeKits($player);
		    unset($this->players[$player->getName()]);
		}
	}
	
	public function onQuit(PlayerQuitEvent $event) {
		
		$player = $event->getPlayer();
		$player->getArmorInventory()->clearAll();
		$player->setAllowFlight(false);
		$player->setGamemode($this->getServer()->getDefaultGamemode());
		$player->getInventory()->clearAll();
		unset($this->removenpcmode[$player->getName()]);
		
		if($player->getLevel()->getFolderName() == $this->getConfig()->get("arena")) {
		   unset($this->players[$player->getName()]);		   
		}		
	}
	
	public function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		if($player->getLevel()->getFolderName() == $this->getConfig()->get("arena")) {
 			$event->setCancelled();
		}
	}
	
	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		if($player->getLevel()->getFolderName() == $this->getConfig()->get("arena")) {
 		    $event->setCancelled();
		}
	}
	
	public function getKits($player){
		$items = $this->getConfig()->get("items"); // Array Items in config.
		$title = $this->getConfig()->get("nameserver");  // Title for server.
		$player->addTitle($title);		
		// Config for armor.
		$leggings = $this->getConfig()->get("leggings");
		$boots = $this->getConfig()->get("boots");
		$helmet = $this->getConfig()->get("helmet");
		$chestplate = $this->getConfig()->get("chestplate");
				
		// Armor for players // 
		$player->getArmorInventory()->setLeggings(Item::get($leggings, 0, 1));
		$player->getArmorInventory()->setBoots(Item::get($boots, 0, 1));
		$player->getArmorInventory()->setHelmet(Item::get($helmet, 0, 1));				
		$player->getArmorInventory()->setChestplate(Item::get($chestplate, 0, 1));
				
		// Items for players
		foreach ($items as $item) {
			$player->getInventory()->addItem(Item::get((int)$item[0], (int)$item[1], (int)$item[2]));								
		}
	}
	
	public function onJoinGameFFA($damager){
		$server = $this->getServer();
		$level = $server->getLevelByName($this->getConfig()->get("arena"));
		$res = $server->loadLevel($this->getConfig()->get("arena"));
		if($res) $level = $server->getLevelByName($this->getConfig()->get("arena"));
		$res = $damager->teleport($level->getSafeSpawn());
		$damager->getArmorInventory()->clearAll();
		$damager->setAllowFlight(false);
		$damager->setGamemode(0);
		$damager->setScale(1.0);
		$damager->getInventory()->clearAll();								
		$this->getKits($damager);
		$this->players[$damager->getName()] = $damager;
	}
	
	public function spawnEntityJoin(Player $player){
        $nbt = new CompoundTag("", [
            new ListTag("Pos", [
            new DoubleTag("", $player->getX()),
            new DoubleTag("", $player->getY()),
            new DoubleTag("", $player->getZ())
        ]),
            new ListTag("Motion", [
            new DoubleTag("", 0),
            new DoubleTag("", 0),
            new DoubleTag("", 0)
        ]),
            new ListTag("Rotation", [
            new FloatTag("",$player->yaw),
            new FloatTag("",$player->pitch)
        ]),
            new CompoundTag("Skin", [
            new StringTag("Data", $player->getSkin()->getSkinData()),
            new StringTag("Name", $player->getSkin()->getSkinId()),
        ]),]);
        $human = new EntityJoinFFA($player->getLevel(), $nbt);
        $human->setScale(1);
        $human->setNametagVisible(true);
        $human->setNameTagAlwaysVisible(true);
        $human->setImmobile(true);
        $human->spawnToAll();
	}
	
	public function onCommand(CommandSender $player, Command $cmd, String $label, array $args) : bool {
		if($cmd->getName() == "ffa"){
			if(count($args) < 1){
				$player->sendMessage(TextFormat::RED . "Usage : /ffa help");
				return false;
			}
			switch($args[0]){
				
				case "join":
				    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
				    $server = $this->getServer();
				    $level = $server->getLevelByName($this->getConfig()->get("arena"));
				    $res = $server->loadLevel($this->getConfig()->get("arena"));
				    if($res) $level = $server->getLevelByName($this->getConfig()->get("arena"));
				        $res = $player->teleport($level->getSafeSpawn());
				        $player->getArmorInventory()->clearAll();
				        $player->setAllowFlight(false);
				        $player->setGamemode(0);
				        $player->setScale(1.0);
				        $player->getInventory()->clearAll();
						
				        $this->getKits($player);
						
						$this->players[$player->getName()] = $player;
				break;
				
				case "quit":
				    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					unset($this->players[$player->getName()]);
					$this->removeKits($player);
					$player->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
				    $player->sendMessage(TextFormat::GREEN . "You have returned to the lobby.");
                break;					
				
				case "npc":
				    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					if(!$player->hasPermission("ffa.cmd.npc")) {
                        $player->sendMessage(TextFormat::RED . $this->notpermission);
                        break;
					}
					$this->spawnEntityJoin($player);
				break;
				
				case "removenpc":
				    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					if(!$player->hasPermission("ffa.cmd.npc")) {
                        $player->sendMessage(TextFormat::RED . $this->notpermission);
                        break;
					}
					$player->sendMessage("Hit an entity to remove it.");
				    $this->removenpcmode[$player->getName()] = 0;
                break;
				
				case "setarena":
                    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					if(!$player->hasPermission("ffa.cmd.arena")) {
                        $player->sendMessage(TextFormat::RED . $this->notpermission);
                        break;
					}
					if(!isset($args[1])) {
                        $player->sendMessage(TextFormat::GREEN . "/ffa setarena [name_arena]");
                        break;
					}
					if(!$this->getServer()->isLevelGenerated($args[1])) {
                        $player->sendMessage(TextFormat::RED . "Level $args[1] does not found!");
                        break;
					}
					$this->getConfig()->set("arena", $args[1]);
					$this->getConfig()->save();
					$player->sendMessage(TextFormat::GREEN . "The world of the arena has been updated!");
				break;
				
				case "settitle":
                    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					if(!$player->hasPermission("ffa.cmd.arena")) {
                        $player->sendMessage(TextFormat::RED . $this->notpermission);
                        break;
					}
					if(!isset($args[1])) {
                        $player->sendMessage(TextFormat::GREEN . "/ffa setarena [name_arena]");
                        break;
					}
					$this->getConfig()->set("nameserver", $args[1]);
					$this->getConfig()->save();
					$player->sendMessage(TextFormat::GREEN . "The title has been updated!");
				break;
				
				case "admin":
				    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					if(!$player->hasPermission("ffa.cmd")) {
                        $player->sendMessage(TextFormat::RED . $this->notpermission);
                        break;
					}
					$player->sendMessage(TextFormat::GREEN . "\n");
					$player->sendMessage(TextFormat::GREEN . "/ffa setarena - Set world arena");
					$player->sendMessage(TextFormat::GREEN . "/ffa settitle - Set title when join arena");
					$player->sendMessage(TextFormat::GREEN . "/ffa npc - Create one NPC to join game");
					$player->sendMessage(TextFormat::GREEN . "/ffa removenpc - Remove one NPC to join game");
                break;
				
				case "about":
				    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					$player->sendMessage(TextFormat::GREEN . "Plugin by DragoVN, Version: " . $this->getDescription()->getVersion());
				break;
				
				case "help":
				    if(!$player instanceof Player) {
                        $player->sendMessage(TextFormat::RED . $this->commandnotconsole);
                        break;
					}
					$player->sendMessage(TextFormat::GREEN . "\n");
                    $player->sendMessage(TextFormat::GREEN . "/ffa admin - Show commands for admin");					
                    $player->sendMessage(TextFormat::GREEN . "/ffa about - Get information of this plugin");				
				    $player->sendMessage(TextFormat::GREEN . "/ffa join - Join the game");
					$player->sendMessage(TextFormat::GREEN . "/ffa quit - Quit the game");			
				break;
				
				default:
				    $player->sendMessage(TextFormat::RED . "Usage : /ffa help");
				break;
			}
		}
		return false;
	}
}