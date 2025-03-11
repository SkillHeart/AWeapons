<?php

declare(strict_types=1);

namespace Weapons\inventory;

use pocketmine\inventory\PlayerInventory as PMPlayerInventory;
use pocketmine\item\Item;

use Weapons\Main;
use Weapons\player\Player;

class PlayerInventory extends PMPlayerInventory{
	
	public function setItem(int $index, Item $item) : void{
        if($item->getCount() > 1 and isset(Main::getInstance()->weapons[$item->getId()])){
        	$rr = clone $item;
        	$this->setItem($index, $rr->setCount(1));
          	$this->addItem($rr->setCount($item->getCount() - 1));
          	return;
        }
		parent::setItem($index, $item);
		if($index === $this->itemInHandIndex and $this->getHolder() instanceof Player){
			$this->getHolder()->reloadTicks = 0;
			$this->getHolder()->reloadTicksMax = 0;
			$this->getHolder()->sendTip("");
		}
		$this->getHolder()->updateModel();
	}

	public function setHeldItemIndex(int $hotbarSlot): void{
		$old = $this->itemInHandIndex;
		parent::setHeldItemIndex($hotbarSlot);
		$this->getHolder()->updateModel($old);
		if($this->getHolder() instanceof Player){
			$this->getHolder()->reloadTicks = 0;
			$this->getHolder()->reloadTicksMax = 0;
			$this->getHolder()->sendTip("");
		}
	}
}