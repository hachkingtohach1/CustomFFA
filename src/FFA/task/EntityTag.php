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
 
namespace FFA\Task;

use pocketmine\scheduler\Task;
use FFA\Main;
use FFA\entity\EntityJoinFFA;
use pocketmine\plugin\Plugin;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\math\Vector2;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\entity\Human;
use pocketmine\utils\TextFormat;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class EntityTag extends Task
{
    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }
    public function onRun(int $currentTick)
    {
        foreach ($this->plugin->getServer()->getLevels() as $level)
        {
            foreach ($level->getEntities() as $entity)
            {
                if ($entity instanceof EntityJoinFFA)
                {
					$i=0; 
                    foreach($this->plugin->players as $player){
                        $i++;
					}				
					$entity->setNameTag($this->plugin->getConfig()->get("title_npc").str_replace("%count", $i, );
                    $entity->getInventory()->setHeldItemIndex(0);
                    $entity->setNameTagAlwaysVisible(true);
                    $this->sendMovement($entity);
                }
            }
        }
    }
	
    public function sendMovement(Entity $entity)
    {
        foreach ($entity->getLevel()->getNearbyEntities($entity->getBoundingBox()->expandedCopy(15, 15, 15) , $entity) as $player)
        {
            if (!$player instanceof Player)
            {
                return true;
            }
            $xdiff = $player->x - $entity->x;
            $zdiff = $player->z - $entity->z;
            $angle = atan2($zdiff, $xdiff);
            $yaw = (($angle * 180) / M_PI) - 90;
            $ydiff = $player->y - $entity->y;
            $v = new Vector2($entity->x, $entity->z);
            $dist = $v->distance($player->x, $player->z);
            $angle = atan2($dist, $ydiff);
            $pitch = (($angle * 180) / M_PI) - 90;
            $pk = new MovePlayerPacket();
            $pk->entityRuntimeId = $entity->getId();
            $pk->position = $entity->asVector3()->add(0, $entity->getEyeHeight() , 0);
            $pk->yaw = $yaw;
            $pk->pitch = $pitch;
            $pk->headYaw = $yaw;
            $pk->onGround = $entity->onGround;
            $player->dataPacket($pk);
        }
    }
}

