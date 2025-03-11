<?php

namespace Weapons;

use pocketmine\scheduler\Task;
use pocketmine\plugin\Plugin;

class PluginCallbackTask extends Task{

	protected $callable;
	protected $args;

	public function __construct(callable $callable, array $args = []){
		$this->callable = $callable;
		$this->args = $args;
		$this->args[] = $this;
	}

	public function onRun(): void{
		call_user_func_array($this->callable, $this->args);
	}
}
