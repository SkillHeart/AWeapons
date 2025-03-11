<?php

declare(strict_types=1);

namespace Weapons\item;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\utils\Binary;

use Weapons\Main;

class CustomItem extends Item{

	public $maxStackSize;
	private $nbt = null;
	
	public function __construct(int $id, int $meta = 0, string $name = "Unknown", int $maxStackSize = 1){
		parent::__construct(new ItemIdentifier($id, $meta), $name);
		$this->maxStackSize = $maxStackSize;
	}

	public function getMaxStackSize() : int{
		return $this->maxStackSize;
	}

	public function nbtSerialize(int $slot = -1) : CompoundTag{
		$id = 0;
		if($this->getId() >= 20000){	
			$str = Main::getInstance()->itemsToString[$this->getId()];
			$str = explode(":", $str);
			$str = $str[1];
			$str = explode("_", $str);
			array_shift($str);
			$str = implode("_", $str);
			$id = Main::getInstance()->weapons[$str]["id"];
		}
		$result = CompoundTag::create()
			->setShort("id", $this->getId() >= 20000 ? $id : $this->getId())
			->setByte("Count", Binary::signByte($this->count))
			->setShort("Damage", $this->getMeta());

		if($this->hasNamedTag()){
			$result->setTag("tag", $this->getNamedTag());
		}

		if($slot !== -1){
			$result->setByte("Slot", $slot);
		}

		return $result;
	}
}