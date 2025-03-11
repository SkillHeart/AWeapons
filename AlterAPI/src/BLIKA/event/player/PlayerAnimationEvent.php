<?php

declare(strict_types=1);

namespace BLIKA\event\player;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\player\Player;

use BLIKA\API;

class PlayerAnimationEvent extends PluginEvent{
	public $player;
	public $animationType;

	public function __construct(Player $player, int $animation){
		parent::__construct(API::getInstance());
		$this->player = $player;
		$this->animationType = $animation;
	}

	public function getPlayer(): Player{
		return $this->player;
	}

	public function getAnimationType() : int{
		return $this->animationType;
	}
}