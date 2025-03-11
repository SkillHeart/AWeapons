<?php

declare(strict_types=1);

namespace BLIKA\particle;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\world\particle\Particle;

class GenericParticle implements Particle{

	protected $id;
	protected $data;

	public function __construct(int $id, int $data = 0){
		$this->id = $id & 0xFFF;
		$this->data = $data;
	}

	public function encode(Vector3 $pos): array{
		$pk = new LevelEventPacket;
		$pk->eventId = LevelEvent::ADD_PARTICLE_MASK | $this->id;
		$pk->position = $pos;
		$pk->eventData = $this->data;
		return [$pk];
	}
}