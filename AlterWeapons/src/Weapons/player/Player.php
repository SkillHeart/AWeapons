<?php

declare(strict_types=1);

namespace Weapons\player;

use pocketmine\entity\Living;
use pocketmine\entity\HungerManager;
use pocketmine\entity\ExperienceManager;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\data\java\GameModeIdMap;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\inventory\EntityInventoryEventProcessor;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\utils\Limits;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player as PMPlayer;
use pocketmine\nbt\tag\CompoundTag;

use Weapons\inventory\PlayerInventory;
use Weapons\Main;

class Player extends PMPlayer{
	
	public int $updateChunks = 0;
	public $prevId = -1;
	public $damagedone = 0;
	public $damagedoneticks = 0;
	public $reloadTicks = 0;
	public $reloadTicksMax = 0;
	public $variant = 0;

	public function updateModel(?int $oldIndex = null): void{
		if($this->getInventory() === null){
			return;
		}
		if($this->prevId === $this->getInventory()->getItemInHand()->getId()){
			return;
		}
		$this->prevId = $this->getInventory()->getItemInHand()->getId();
		if($oldIndex !== null and $this->getInventory()->getItem($oldIndex)->getId() >= 20000){
			$str = Main::getInstance()->itemsToString[$this->getInventory()->getItem($oldIndex)->getId()];
			$str = explode(":", $str);
			$str = $str[1];
			$str = explode("_", $str);
			array_shift($str);
			$str = implode("_", $str);
			$item = ItemFactory::getInstance()->get(Main::getInstance()->weapons[$str]["id"], 0);
			if(($t = $this->getInventory()->getItem($oldIndex)->getNamedTag()->getTag("ammo")) !== null){
				$tag = $item->getNamedTag(); $tag->setTag("ammo", $t);
				$item->setNamedTag($tag);
			}
			$this->getInventory()->setItem($oldIndex, $item);
		}
		if(isset(Main::getInstance()->weapons[$this->getInventory()->getItemInHand()->getId()]) and isset(Main::getInstance()->weapons[$this->getInventory()->getItemInHand()->getId()]["model"])){
			$this->getArmorInventory()->setBoots(ItemFactory::getInstance()->get(Main::getInstance()->items["stalcraft:attachable_".Main::getInstance()->weapons[$this->getInventory()->getItemInHand()->getId()]["section"]]));
			$this->variant = Main::getInstance()->weapons[$this->getInventory()->getItemInHand()->getId()]["one_handed"] ? 1 : 2;
			$item = ItemFactory::getInstance()->get(Main::getInstance()->items["stalcraft:attachable_".Main::getInstance()->weapons[$this->getInventory()->getItemInHand()->getId()]["section"]]);
			if(($t = $this->getInventory()->getItemInHand()->getNamedTag()->getTag("ammo")) !== null){
				$tag = $item->getNamedTag(); $tag->setTag("ammo", $t);
				$item->setNamedTag($tag);
			}
			$this->getInventory()->setItemInHand($item);
		}else{
			$this->getArmorInventory()->setBoots(ItemFactory::getInstance()->get(0, 0));
			$this->variant = 0;
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::MARK_VARIANT, $this->variant);
	}
	
	public function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->damagedoneticks > 0){
			$this->damagedoneticks -= $tickDiff;
			if($this->damagedoneticks <= 0){
				$this->damagedone = 0;
			}
		}
		if($this->reloadTicks > 0){
			$res = $this->reloadTicksMax - $this->reloadTicks;
			$perc = $res / $this->reloadTicksMax;
			$cnt = 20;
			$int = intval($cnt * $perc);
			$left = $cnt - $int;
			$item = $this->getInventory()->getItemInHand();
			if($left == 0){
				$text = "[".str_repeat("§a|§f", $cnt)."]";
			}elseif($int == 0){
				$text = "[".str_repeat("§c|§f", $cnt)."]";
			}else{
				$text = "[".str_repeat("§a|§f", $int)."".str_repeat("§c|§f", $left)."]";
			}
			$this->sendTip($text);
			$this->reloadTicks -= $tickDiff;
			if($this->reloadTicks <= 0){
				$this->sendTip("");
				Main::getInstance()->reload($this);
			}
		}
		return $hasUpdate;
	}

	public function getDrops(): array{
		if($this->isCreative()){
			return [];
		}
		if($this->inventory !== null){
			return $this->inventory->getContents();
		}
		return [];
	}

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		$tickDiff = $currentTick - $this->lastUpdate;

		if($tickDiff <= 0){
			return true;
		}
		if($this->updateChunks > 0){
			$this->updateChunks -= $tickDiff;
			if($this->updateChunks <= 0){
				$this->setViewDistance(0); //dunno if we need this
				$this->setViewDistance($this->viewDistance);
			}
		}
	}

	public function getSaveData(): CompoundTag{
		if($this->inventory !== null){
			foreach($this->inventory->getContents() as $i => $s){
				if($s->getId() >= 20000){
					$str = Main::getInstance()->itemsToString[$s->getId()];
					$str = explode(":", $str);
					$str = $str[1];
					$str = explode("_", $str);
					array_shift($str);
					$str = implode("_", $str);
					$item = ItemFactory::getInstance()->get(Main::getInstance()->weapons[$str]["id"], 0);
					if(($t = $s->getNamedTag()->getTag("ammo")) !== null){
						$tag = $item->getNamedTag(); $tag->setTag("ammo", $t);
						$item->setNamedTag($tag);
					}
					$this->inventory->setItem($i, $item);
				}
			}
		}
		return parent::getSaveData();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		Living::initEntity($nbt);

		$this->hungerManager = new HungerManager($this);
		$this->xpManager = new ExperienceManager($this);

		$this->inventory = new PlayerInventory($this);
		$syncHeldItem = function() : void{
			foreach($this->getViewers() as $viewer){
				$viewer->getNetworkSession()->onMobMainHandItemChange($this);
			}
		};
		$this->inventory->getListeners()->add(new CallbackInventoryListener(
			function(Inventory $unused, int $slot, Item $unused2) use ($syncHeldItem) : void{
				if($slot === $this->inventory->getHeldItemIndex()){
					$syncHeldItem();
				}
			},
			function(Inventory $unused, array $oldItems) use ($syncHeldItem) : void{
				if(array_key_exists($this->inventory->getHeldItemIndex(), $oldItems)){
					$syncHeldItem();
				}
			}
		));
		$this->offHandInventory = new PlayerOffHandInventory($this);
		$this->enderInventory = new PlayerEnderInventory($this);
		$this->initHumanData($nbt);

		$inventoryTag = $nbt->getListTag("Inventory");
		if($inventoryTag !== null){
			$armorListeners = $this->armorInventory->getListeners()->toArray();
			$this->armorInventory->getListeners()->clear();
			$inventoryListeners = $this->inventory->getListeners()->toArray();
			$this->inventory->getListeners()->clear();

			/** @var CompoundTag $item */
			foreach($inventoryTag as $i => $item){
				$slot = $item->getByte("Slot");
				if($slot >= 0 and $slot < 9){ //Hotbar
					//Old hotbar saving stuff, ignore it
				}elseif($slot >= 100 and $slot < 104){ //Armor
					$this->armorInventory->setItem($slot - 100, Item::nbtDeserialize($item));
				}elseif($slot >= 9 and $slot < $this->inventory->getSize() + 9){
					$this->inventory->setItem($slot - 9, Item::nbtDeserialize($item));
				}
			}

			$this->armorInventory->getListeners()->add(...$armorListeners);
			$this->inventory->getListeners()->add(...$inventoryListeners);
		}
		$offHand = $nbt->getCompoundTag("OffHandItem");
		if($offHand !== null){
			$this->offHandInventory->setItem(0, Item::nbtDeserialize($offHand));
		}
		$this->offHandInventory->getListeners()->add(CallbackInventoryListener::onAnyChange(function() : void{
			foreach($this->getViewers() as $viewer){
				$viewer->getNetworkSession()->onMobOffHandItemChange($this);
			}
		}));

		$enderChestInventoryTag = $nbt->getListTag("EnderChestInventory");
		if($enderChestInventoryTag !== null){
			/** @var CompoundTag $item */
			foreach($enderChestInventoryTag as $i => $item){
				$this->enderInventory->setItem($item->getByte("Slot"), Item::nbtDeserialize($item));
			}
		}

		$this->inventory->setHeldItemIndex($nbt->getInt("SelectedInventorySlot", 0));
		$this->inventory->getHeldItemIndexChangeListeners()->add(function(int $oldIndex) : void{
			foreach($this->getViewers() as $viewer){
				$viewer->getNetworkSession()->onMobMainHandItemChange($this);
			}
		});

		$this->hungerManager->setFood(0);
		$this->hungerManager->addFood((float) $nbt->getInt("foodLevel", (int) $this->hungerManager->getFood()));
		$this->hungerManager->setExhaustion($nbt->getFloat("foodExhaustionLevel", $this->hungerManager->getExhaustion()));
		$this->hungerManager->setSaturation($nbt->getFloat("foodSaturationLevel", $this->hungerManager->getSaturation()));
		$this->hungerManager->setFoodTickTimer($nbt->getInt("foodTickTimer", $this->hungerManager->getFoodTickTimer()));

		$this->xpManager->setXpAndProgressNoEvent(
			$nbt->getInt("XpLevel", 0),
			$nbt->getFloat("XpP", 0.0));
		$this->xpManager->setLifetimeTotalXp($nbt->getInt("XpTotal", 0));

		if(($xpSeedTag = $nbt->getTag("XpSeed")) instanceof IntTag){
			$this->xpSeed = $xpSeedTag->getValue();
		}else{
			$this->xpSeed = random_int(Limits::INT32_MIN, Limits::INT32_MAX);
		}
		$this->addDefaultWindows();

		$this->inventory->getListeners()->add(new CallbackInventoryListener(
			function(Inventory $unused, int $slot) : void{
				if($slot === $this->inventory->getHeldItemIndex()){
					$this->setUsingItem(false);
				}
			},
			function() : void{
				$this->setUsingItem(false);
			}
		));

		$this->firstPlayed = $nbt->getLong("firstPlayed", $now = (int) (microtime(true) * 1000));
		$this->lastPlayed = $nbt->getLong("lastPlayed", $now);

		if(!$this->server->getForceGamemode() and ($gameModeTag = $nbt->getTag("playerGameType")) instanceof IntTag){
			$this->internalSetGameMode(GameModeIdMap::getInstance()->fromId($gameModeTag->getValue()) ?? GameMode::SURVIVAL()); //TODO: bad hack here to avoid crashes on corrupted data
		}else{
			$this->internalSetGameMode($this->server->getGamemode());
		}

		$this->keepMovement = true;

		$this->setNameTagVisible();
		$this->setNameTagAlwaysVisible();
		$this->setCanClimb();

		if(($world = $this->server->getWorldManager()->getWorldByName($nbt->getString("SpawnLevel", ""))) instanceof World){
			$this->spawnPosition = new Position($nbt->getInt("SpawnX"), $nbt->getInt("SpawnY"), $nbt->getInt("SpawnZ"), $world);
		}
	}
}