<?php

declare(strict_types=1);

namespace Weapons\entity;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Throwable;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\math\RayTraceResult;
use Weapons\Explosion;

class RocketProjectile extends Throwable{

    public static function getNetworkTypeId() : string{ return EntityIds::ARROW; }

    public $explDamage = 0;
    public $shooter = null;

	protected function onHit(ProjectileHitEvent $event) : void{
        (new Explosion($this->getPosition(), 10, $this->shooter))->explodeB($this->explDamage);
        $this->flagForDespawn();
	}

    public function canSaveWithChunks(): bool{
        return false;
    }

    protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
    }

    protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
    }
}