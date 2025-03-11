<?php

declare(strict_types=1);

namespace Weapons;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntityDataHelper;
use pocketmine\world\World;
use pocketmine\data\bedrock\EntityLegacyIds;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use BLIKA\event\player\PlayerAnimationEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CreativeInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\world\Position;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\VoxelRayTrace;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

use Weapons\entity\RocketProjectile;
use Weapons\item\CustomItem;
use Weapons\player\Player;

use NPC\entities\NPC;
use czechpmdevs\multiworld\world\dimension\Dimension;

use ReflectionClass;

class Main extends PluginBase implements Listener{

	private static $instance = null;
	public $entries = [];
	public $items = [];
	public $itemsToString = [];
	public $weapons = [];
	public $ticks = [];

	public function onLoad(): void{
		self::$instance = $this;
	}

  	public static function getInstance() : self{
    	return self::$instance;
  	}

	public function loadAttachables(): void{
		$dataPath = $this->getDataFolder()."resources/";
	    $startPath = $dataPath."weapons/";
		$usedPaths = [];
		$paths = [];
		$previousPath = null;
		$id = 20000;
		$example = json_decode(file_get_contents($dataPath."example_held.json"), true);
		$examplePlayer = json_decode(file_get_contents($dataPath."example_held.player.json"), true);
		while(true){
			if(empty($paths)){
				$currentPath = $startPath;
			}else{
				$currentPath = array_shift($paths);
			}
			if($currentPath == $previousPath){
				break;
			}
			$scan = scandir($currentPath);
			unset($scan[0]);
			unset($scan[1]);
			foreach($scan as $s){
				$path = $currentPath.$s;
				$file = pathinfo($path)["filename"];
				if(in_array($path, $usedPaths) or in_array($path."/", $usedPaths)){
					continue;
				}
				if(is_dir($path)){
					$paths[] = $path."/";
					$usedPaths[] = $path."/";
				}elseif(pathinfo($path)["extension"] == "json"){
					$data = json_decode(file_get_contents($path), true);
					if(isset($data["model"])){
						$model = $data["model"];
						$geometry = json_decode(file_get_contents($dataPath."models/".$model.".json"), true);
						$identifier = $geometry["minecraft:geometry"][0]["description"]["identifier"];
						file_put_contents($dataPath."export_models/".$model.".json", json_encode($geometry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
						$behind = $example;
						$behind["minecraft:attachable"]["description"]["identifier"] = "stalcraft:attachable_".$file;
						$behind["minecraft:attachable"]["description"]["textures"]["default"] = "textures/weapons/".$file;
						$behind["minecraft:attachable"]["description"]["geometry"]["default"] = $identifier;
						$behind["minecraft:attachable"]["description"]["render_controllers"] = ["controller.render.player.weapon"];
						file_put_contents($dataPath."attachables/".$file.".json", json_encode($behind, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
						$behindPlayer = $examplePlayer;
						$behindPlayer["minecraft:attachable"]["description"]["identifier"] = "stalcraft:attachable_".$file.".player";
						$behindPlayer["minecraft:attachable"]["description"]["item"] = ["stalcraft:attachable_".$file => "query.owner_identifier == 'minecraft:player'"];
						$behindPlayer["minecraft:attachable"]["description"]["scripts"]["animate"][] = "weapon";
						$behindPlayer["minecraft:attachable"]["description"]["animations"]["weapon"] = "animation.player.hand.".$file;
						$behindPlayer["minecraft:attachable"]["description"]["textures"]["default"] = "textures/weapons/".$file;
						$behindPlayer["minecraft:attachable"]["description"]["geometry"]["default"] = $identifier;
						$behindPlayer["minecraft:attachable"]["description"]["render_controllers"] = ["controller.render.player.weapon"];
						file_put_contents($dataPath."attachables/".$file.".player.json", json_encode($behindPlayer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
						$this->weapons[$id] = $this->weapons[$data["id"]];
						$this->items["stalcraft:attachable_".$file] = $id;
						$this->itemsToString[$id] = "stalcraft:attachable_".$file;
						$id++;
					}
					$usedPaths[] = $path;
				}
			}
			$previousPath = $currentPath;
		}
	}

	public function removeOldFiles(string $startPath): void{
		$usedPaths = [];
		$paths = [];
		$previousPath = null;
		while(true){
			if(empty($paths)){
				$currentPath = $startPath;
			}else{
				$currentPath = array_shift($paths);
			}
			if($currentPath == $previousPath){
				break;
			}
			$scan = scandir($currentPath);
			unset($scan[0]);
			unset($scan[1]);
			foreach($scan as $s){
				$path = $currentPath.$s;
				$file = pathinfo($path)["filename"];
				if(in_array($path, $usedPaths) or in_array($path."/", $usedPaths)){
					continue;
				}
				if(is_dir($path)){
					$paths[] = $path."/";
					$usedPaths[] = $path."/";
				}else{
					unlink($path);
					$usedPaths[] = $path;
				}
			}
			$previousPath = $currentPath;
		}
	}

	public function onEnable(): void{
		if(!file_exists($this->getDataFolder()."resources/")){
			mkdir($this->getDataFolder()."resources/");
		}
		if(!file_exists($this->getDataFolder()."resources/weapons/")){
			mkdir($this->getDataFolder()."resources/weapons/");
		}
		if(!file_exists($this->getDataFolder()."resources/attachables/")){
			mkdir($this->getDataFolder()."resources/attachables/");
		}
		if(!file_exists($this->getDataFolder()."resources/models/")){
			mkdir($this->getDataFolder()."resources/models/");
		}
		if(!file_exists($this->getDataFolder()."resources/export_models/")){
			mkdir($this->getDataFolder()."resources/export_models/");
		}
		if(!file_exists($this->getDataFolder()."resources/custom_items.json")){
			file_put_contents($this->getDataFolder()."resources/custom_items.json", json_encode(["stalcraft:example" => 10000], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		}
		$this->items = json_decode(file_get_contents($this->getDataFolder()."resources/custom_items.json"), true);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new PluginCallbackTask([$this, "doTick"], []), 1);
		$this->loadWeapons();
		$this->removeOldFiles($this->getDataFolder()."resources/attachables/");
		$this->removeOldFiles($this->getDataFolder()."resources/export_models/");
		$this->loadAttachables();
	    EntityFactory::getInstance()->register(RocketProjectile::class, function(World $world, CompoundTag $nbt) : RocketProjectile{
	      return new RocketProjectile(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
	    }, ['RocketProjectile'], EntityLegacyIds::ARROW);
	    //in 4.0 there's a better way to do this but this way you at least won't be confused with it
		$r = new ReflectionClass(ItemTranslator::class);
		$rp = $r->getProperty("simpleCoreToNetMapping");
		$rp->setAccessible(true);
		$arr = $rp->getValue(ItemTranslator::getInstance());
		foreach($this->items as $s => $i){
			$arr[$i] = $i;
		}
		$rp->setValue(ItemTranslator::getInstance(), $arr);
		$rp = $r->getProperty("simpleNetToCoreMapping");
		$rp->setAccessible(true);
		$arr = $rp->getValue(ItemTranslator::getInstance());
		foreach($this->items as $s => $i){
			$arr[$i] = $i;
		}
		$rp->setValue(ItemTranslator::getInstance(), $arr);
		$r = new ReflectionClass(GlobalItemTypeDictionary::class);

		$dictionaryPr = $r->getProperty("dictionary");
		$dictionaryPr->setAccessible(true);
		$cl = $dictionaryPr->getValue(GlobalItemTypeDictionary::getInstance());
		$dictionary = new ReflectionClass($cl);

		$rp = $dictionary->getProperty("stringToIntMap");
		$rp->setAccessible(true);
		$arr = $rp->getValue($cl);
		foreach($this->items as $s => $i){
			$arr[$s] = $i;
		}
		$rp->setValue($dictionary, $arr);
		$rp = $dictionary->getProperty("intToStringIdMap");
		$rp->setAccessible(true);
		$arr = $rp->getValue($cl);
		foreach($this->items as $s => $i){
			$arr[$i] = $s;
		}
		$rp->setValue($dictionary, $arr);
		$this->entries = GlobalItemTypeDictionary::getInstance()->getDictionary()->getEntries();
		foreach($this->items as $s => $i){
			$this->entries[] = new ItemTypeEntry($s, $i, false);
		}
		foreach($this->items as $i => $id){
			$item = new CustomItem($id, 0, $i, (isset($this->weapons[$id]) or $id >= 20000) ? 1 : 64);
			ItemFactory::getInstance()->register($item);
			if($id >= 20000){
				continue;
			}
			CreativeInventory::getInstance()->add($item);
		}
	}

	public function loadWeapons(): void{
	    $startPath = $this->getDataFolder()."resources/weapons/";
	    $usedPaths = [];
	    $paths = [];
	    $previousPath = null;
	    while(true){
	      	if(empty($paths)){
	        	$currentPath = $startPath;
	      	}else{
	        	$currentPath = array_shift($paths);
	      	}
	      	if($currentPath == $previousPath){
	        	break;
	      	}
	      	$scan = scandir($currentPath);
	      	unset($scan[0]);
	      	unset($scan[1]);
	      	foreach($scan as $s){
	        	$path = $currentPath.$s;
	        	$file = pathinfo($path)["filename"];
	        	if(in_array($path, $usedPaths) or in_array($path."/", $usedPaths)){
	          		continue;
	        	}
	        	if(is_dir($path)){
	          		$paths[] = $path."/";
	          		$usedPaths[] = $path."/";
	        	}elseif(pathinfo($path)["extension"] == "json"){
	          		$data = json_decode(file_get_contents($path), true);
	          		$data["section"] = $file;
	          		$this->weapons[$data["id"]] = $data;
	          		$this->weapons[$file] = $data;
	          		$usedPaths[] = $path;
	        	}
	    	}
	   	 	$previousPath = $currentPath;
	    }
    }

	public function doTick(): void{
		foreach($this->ticks as $p => $t){
			$this->ticks[$p]--;
			if($this->ticks[$p] <= 0){
				unset($this->ticks[$p]);
			}
		}
	}

	public static function rayCast(Entity $sender, float $distance) : ?array{
		$item = $sender->getInventory()->getItemInHand();
		$motion = $sender->getDirectionVector()->multiply(0.5);
		$pos = $sender->getPosition()->add(0, $sender->size->getHeight() - 0.15, 0);
		$currentPos = $pos;
		$add = $motion;
		$distanceE = intval(round($distance));
		for($i = 1; $i++; $i <= $distanceE){
			if(!$sender->getWorld()->isChunkInUse($currentPos->x >> 4,  $currentPos->z >> 4)){
				break;
			}
			if($pos->distance($currentPos) >= $distance){
				break;
			}
			$boundingBox = new AxisAlignedBB($currentPos->x, $currentPos->y, $currentPos->z, $currentPos->x, $currentPos->y, $currentPos->z);
			$end = $currentPos->add($add->x, $add->y, $add->z);
			foreach($sender->getWorld()->getCollidingEntities($boundingBox->expandedCopy(0.3, 0.3, 0.3)) as $entity){
				if($entity->getId() == $sender->getId()){
					continue;
				}
				$entityBB = $entity->boundingBox->expandedCopy(0.3, 0.3, 0.3);
				$entityHitResult = $entityBB->calculateIntercept($currentPos, $end);
				if($entityHitResult === null){
					continue;
				}
				$distanceD = $currentPos->distanceSquared($entityHitResult->hitVector);
				if($distanceD < $distance){
					return ["entity" => $entity, "distance" => $distanceD, "reached" => $entityHitResult->hitVector];
				}
			}
			foreach(VoxelRayTrace::betweenPoints($currentPos, $end) as $vector3){
				$block = $sender->getWorld()->getBlockAt($vector3->x, $vector3->y, $vector3->z);
				$blockHitResult = $block->calculateIntercept($currentPos, $end);
				if($blockHitResult !== null){
					$sender->getWorld()->addParticle($blockHitResult->hitVector, new BlockBreakParticle($block));
					return ["block" => $block, "reached" => $blockHitResult->hitVector];
					break;
				}
			}
			$currentPos = $end;
			$sender->getWorld()->addParticle($currentPos, new AngryVillagerParticle());
		}
		return null;
	}

	public function playSound(Position $pos, string $name, float $volume = 1, float $pitch = 1, bool $global = false): void{
		$pk = new PlaySoundPacket();
		$pk->soundName = $name;
		$pk->x = $pos->getX();
		$pk->y = $pos->getY();
		$pk->z = $pos->getZ();
		$pk->volume = $volume;
		$pk->pitch = $pitch;
		if($global){
			$pos->getWorld()->broadcastGlobalPacket($pk);
		}else{
			$pos->getWorld()->broadcastPacketToViewers($pos, $pk);
		}
	}

	public function checkWeapon(Item &$item, array $data): void{
		$tag = $item->getNamedTag();
		if($tag->getTag("ammo") === null){
			$tag->setLong("ammo", $data["mag_size"]);
			$item->setNamedTag($tag);
		}
	}

	public function onHeld(PlayerItemHeldEvent $event): void{
		if($event->getPlayer()->reloadTicks > 0){
			$event->getPlayer()->reloadTicks = 0;
			$event->getPlayer()->reloadTicksMax = 0;
			$event->getPlayer()->sendTip("");
		}
	}

	public function shoot(Entity $player, array $ttx): void{
		$item = $player->getInventory()->getItemInHand();
		$tag = $item->getNamedTag();
		if($player instanceof Player){
			if($tag->getTag("ammo") === null){
				$tag->setLong("ammo", $ttx["mag_size"]);
				$item->setNamedTag($tag);
	    		$player->getInventory()->setItemInHand($item);
			}
			if($tag->getTag("ammo")->getValue() <= 0){
				return;
			}
		}
		if(!isset($ttx["class"])){
		    $result = $this->rayCast($player, $ttx["distance"]);
		    if($result !== null){
		      	if(isset($result["entity"]) and ($result["entity"] instanceof Player or $result["entity"] instanceof NPC)){
			        $ev = new EntityDamageByEntityEvent($player, $result["entity"], 2, $ttx["damage"]);
			        $hpwas = $result["entity"]->getHealth();
			        $result["entity"]->attack($ev);
			        $hp = $hpwas - $result["entity"]->getHealth();
					if($player instanceof Player){
				        $player->damagedone += $hp;
				        $player->damagedoneticks = 60;
				        $player->sendPopup("Нанесено ".$player->damagedone." урона");
				    }
		      	}
		    }
		}elseif($ttx["class"] == "rpg"){
			$loc = $player->getLocation(); $loc->y += 1.62;
			$rocket = new RocketProjectile($loc, $player);
			$rocket->shooter = $player;
			$rocket->explDamage = $ttx["damage"];
			$rocket->setMotion($player->getDirectionVector()->multiply(2.5));
			$rocket->spawnToAll();
		}
		if(!($player instanceof Player)){
	    	$this->playSound($player->getPosition(), $ttx["sound"]);
			return;
		}
	    if(isset($this->ticks[$player->getName()])){
	    	$this->ticks[$player->getName()] += $ttx["delay"];
	    }else{
	    	$this->ticks[$player->getName()] = $ttx["delay"];
	    }
    	$tag->setLong("ammo",$tag->getTag("ammo")->getValue()-1);
    	$item->setNamedTag($tag);
    	$player->getInventory()->setItemInHand($item);
	    $this->playSound($player->getPosition(), $ttx["sound"]);
	    $player->sendPopup($tag->getLong("ammo")."/".$ttx["mag_size"]);
	}

	public function reload(Player $player): void{
		if($player->getInventory() === null){
			return;
		}
		$item = $player->getInventory()->getItemInHand();
		if($item->getNamedTag()->getTag("ammo") === null){
			return;
		}
		if(!isset($this->weapons[$item->getId()])){
			return;
		}
		$data = $this->weapons[$item->getId()];
		$ammo = ItemFactory::getInstance()->get($data["ammo"]["id"], 0);
	    $slot = $player->getInventory()->first($ammo);
	    if($slot === -1){
	    	return;
	    }
	    $tag = $item->getNamedTag();
    	while($tag->getTag("ammo")->getValue() < $data["mag_size"] and ($slot = $player->getInventory()->first($ammo)) !== -1){
    		$need = $data["mag_size"] - $tag->getTag("ammo")->getValue();
    		$count = $player->getInventory()->getItem($slot)->getCount();
		    if($count >= $need){
    			$tag->setLong("ammo", $data["mag_size"]);
		    	$item->setNamedTag($tag);
		    	$player->getInventory()->setItem($slot, $player->getInventory()->getItem($slot)->setCount($player->getInventory()->getItem($slot)->getCount() - $need));
		    }else{
    			$tag->setLong("ammo", $tag->getTag("ammo")->getValue()+$player->getInventory()->getItem($slot)->getCount());
		    	$item->setNamedTag($tag);
		    	$player->getInventory()->setItem($slot, $player->getInventory()->getItem($slot)->setCount(0));
		    }
		}
	    $player->getInventory()->setItemInHand($item);
	}

  	public function onLMB(PlayerAnimationEvent $event): void{
	    $player = $event->getPlayer();
	    if($player->reloadTicks > 0){
	    	return;
	    }
	    if(isset($this->ticks[$player->getName()])){
	      return;
	    }
	    $item = $player->getInventory()->getItemInHand();
	    if(!isset($this->weapons[$item->getId()])){
	    	return;
	    }
	    $conf = $this->weapons[$item->getId()];
	    $this->checkWeapon($item, $conf);
	    $player->getInventory()->setItemInHand($item);
	    $tag = $item->getNamedTag();
	    if($tag->getTag("ammo")->getValue() <= 0 and !isset($this->reloading[$player->getName()])){
		    $ammo = ItemFactory::getInstance()->get($conf["ammo"]["id"], 0);
	    	if($player->getInventory()->first($ammo) == -1){
		    	$player->sendPopup("Закончились патроны");
	    		return;
	    	}
	    	$player->reloadTicks = $conf["reload"];
	    	$player->reloadTicksMax = $conf["reload"];
	    	$this->playSound($player->getPosition(), $conf["reload_sound"]);
	    	return;
	    }
	    for($i = 0; $i < $conf["lmb"]; $i++){
         	$this->getScheduler()->scheduleDelayedTask(new PluginCallbackTask([$this, "shoot"], [$player, $conf]), $conf["delay"] * $i);
	    }
  	}

  	public function onRMB(PlayerItemUseEvent $event): void{
	    $player = $event->getPlayer();
	    if($player->reloadTicks > 0){
	    	return;
	    }
	    if(isset($this->ticks[$player->getName()])){
	      return;
	    }
	    $item = $player->getInventory()->getItemInHand();
	    if(!isset($this->weapons[$item->getId()])){
	    	return;
	    }
	    $conf = $this->weapons[$item->getId()];
	    $this->checkWeapon($item, $conf);
	    $player->getInventory()->setItemInHand($item);
	    $tag = $item->getNamedTag();
	    if($tag->getTag("ammo")->getValue() <= 0 and !isset($this->reloading[$player->getName()])){
		    $ammo = ItemFactory::getInstance()->get($conf["ammo"]["id"], 0);
	    	if($player->getInventory()->first($ammo) == -1){
		    	$player->sendPopup("Закончились патроны");
	    		return;
	    	}
	    	$player->reloadTicks = $conf["reload"];
	    	$player->reloadTicksMax = $conf["reload"];
	    	$this->playSound($player->getPosition(), $conf["reload_sound"]);
	    	return;
	    }
	    for($i = 0; $i < $conf["rmb"]; $i++){
         	$this->getScheduler()->scheduleDelayedTask(new PluginCallbackTask([$this, "shoot"], [$player, $conf]), $conf["delay"] * $i);
	    }
  	}

  	public function onRMBBlock(PlayerInteractEvent $event): void{
	    $player = $event->getPlayer();
	    if($player->reloadTicks > 0){
	    	return;
	    }
	    if(isset($this->ticks[$player->getName()])){
	      return;
	    }
	    $item = $player->getInventory()->getItemInHand();
	    if(!isset($this->weapons[$item->getId()])){
	    	return;
	    }
	    $conf = $this->weapons[$item->getId()];
	    $this->checkWeapon($item, $conf);
	    $player->getInventory()->setItemInHand($item);
	    $tag = $item->getNamedTag();
	    if($tag->getTag("ammo")->getValue() <= 0 and !isset($this->reloading[$player->getName()])){
		    $ammo = ItemFactory::getInstance()->get($conf["ammo"]["id"], 0);
	    	if($player->getInventory()->first($ammo) == -1){
		    	$player->sendPopup("Закончились патроны");
	    		return;
	    	}
	    	$player->reloadTicks = $conf["reload"];
	    	$player->reloadTicksMax = $conf["reload"];
	    	$this->playSound($player->getPosition(), $conf["reload_sound"]);
	    	return;
	    }
	    for($i = 0; $i < $conf["rmb"]; $i++){
         	$this->getScheduler()->scheduleDelayedTask(new PluginCallbackTask([$this, "shoot"], [$player, $conf]), $conf["delay"] * $i);
	    }
  	}

	public function onInventoryTransaction(InventoryTransactionEvent $event): void{
	    $tr = $event->getTransaction();
	    $player = $tr->getSource();
	    foreach($tr->getActions() as $a){
	      	if($a instanceof SlotChangeAction){
          		$source = $a->getSourceItem();
          		$target = $a->getTargetItem();
          		if($source->getId() >= 20000){
            		$event->cancel();
            		$player->getCursorInventory()->setItem(0, $player->getCursorInventory()->getItem(0)->getId() == 0 ? ItemFactory::air() : $player->getCursorInventory()->getItem(0));
          		}
          		if($target->getId() >= 20000){
            		$event->cancel();
            		$player->getCursorInventory()->setItem(0, $player->getCursorInventory()->getItem(0)->getId() == 0 ? ItemFactory::air() : $player->getCursorInventory()->getItem(0));
          		}
	      	}
	    }
	}

	public function onPlayerCreation(PlayerCreationEvent $event): void{
		$event->setPlayerClass(Player::class);
	}

	public function onDataSend(DataPacketSendEvent $event): void{
	    foreach($event->getTargets() as $session){
	      	foreach($event->getPackets() as $packet){
				if($packet instanceof StartGamePacket and count($packet->itemTable) !== count($this->entries)){
					$packet->itemTable = $this->entries;
					$packet->levelSettings->spawnSettings = new SpawnSettings(SpawnSettings::BIOME_TYPE_DEFAULT, "", Dimension::getDimensionByWorld($session->getPlayer()->getWorld()));
				}
			}
		}
	}
}