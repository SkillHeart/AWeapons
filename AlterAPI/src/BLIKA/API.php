<?php

declare(strict_types=1);

namespace BLIKA;

use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\tile\Spawnable;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\entity\EntityTrampleFarmlandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\world\World;
use BLIKA\event\player\PlayerAnimationEvent;
use Alter\inventories\BagInventory;
use Alter\CorpseInventory;

class API extends PluginBase implements Listener{

  public $asyncData = [];
  public $asyncIds = 0;
  private static $instance = null;

  public static function getInstance() : ?self{
    return self::$instance;
  }

  public function onEnable(): void{
    self::$instance = $this;
    if(!file_exists($this->getDataFolder()."resources/")){
      mkdir($this->getDataFolder()."resources");
    }
    if(!file_exists($this->getDataFolder()."resources/scripts/")){
      mkdir($this->getDataFolder()."resources/scripts");
    }
    if(!file_exists($this->getDataFolder()."resources/compiled_scripts/")){
      mkdir($this->getDataFolder()."resources/compiled_scripts");
    }
    $this->removeOldScripts();
    $this->compileScripts();
    $this->getServer()->getPluginManager()->registerEvents($this, $this);
  }

  public function onBlockUpdate(BlockUpdateEvent $event): void{
	if($event->getBlock()->getId() === BlockLegacyIds::SAND or $event->getBlock()->getId() === BlockLegacyIds::GRAVEL or $event->getBlock()->getId() === BlockLegacyIds::CONCRETEPOWDER or $event->getBlock()->getId() === BlockLegacyIds::CONCRETE_POWDER or ($event->getBlock()->getId() >= 386 and $event->getBlock()->getId() <= 392)){
		$event->cancel();
	}
  }

  public function onEntityTrampleFarm(EntityTrampleFarmlandEvent $event): void{
	$event->cancel();
  }

  public function onBlockSpread(BlockSpreadEvent $event): void{
	$event->cancel();
  }

  public function onDataSend(DataPacketSendEvent $event): void{
    foreach($event->getTargets() as $session){
      foreach($event->getPackets() as $packet){
        if(!($packet instanceof ContainerOpenPacket)){
          continue;
        }
        $inv = $session->getInvManager()->getWindow($packet->windowId);
        switch(true){
          case $inv instanceof BagInventory:
            switch($inv->type){
              case "hopper":
                if($packet->windowType !== WindowTypes::HOPPER){
                  $event->cancel();
                  $session->sendDataPacket(ContainerOpenPacket::blockInv($packet->windowId, WindowTypes::HOPPER, BlockPosition::fromVector3($inv->getHolder())));
                }
              break;
              case "dropper":
                if($packet->windowType !== WindowTypes::DROPPER){
                  $event->cancel();
                  $session->sendDataPacket(ContainerOpenPacket::blockInv($packet->windowId, WindowTypes::DROPPER, BlockPosition::fromVector3($inv->getHolder())));
                }
              break;
              case "dispenser":
                if($packet->windowType !== WindowTypes::DISPENSER){
                  $event->cancel();
                  $session->sendDataPacket(ContainerOpenPacket::blockInv($packet->windowId, WindowTypes::DISPENSER, BlockPosition::fromVector3($inv->getHolder())));
                }
              break;
              default:
                if($packet->windowType !== WindowTypes::CONTAINER){
                  $event->cancel();
                  $session->sendDataPacket(ContainerOpenPacket::blockInv($packet->windowId, WindowTypes::CONTAINER, BlockPosition::fromVector3($inv->getHolder())));
                }
              break;
            }
          break;
          case $inv instanceof CorpseInventory:
            if($packet->windowType !== WindowTypes::CONTAINER){
              $event->cancel();
              $session->sendDataPacket(ContainerOpenPacket::blockInv($packet->windowId, WindowTypes::CONTAINER, BlockPosition::fromVector3($inv->getHolder())));
            }
          break;
        }
      }
    }
  }

  public function onDataReceive(DataPacketReceiveEvent $event): void{
    if(!(($packet = $event->getPacket()) instanceof AnimatePacket)){
      return;
    }
    (new PlayerAnimationEvent($event->getOrigin()->getPlayer(), $packet->action))->call();
  }

  public function sendBlocks(World $world, array $targets, array $blocks, array $realBlocks = []) : void{
    $packets = [];

    foreach($blocks as $b){
      if(!($b instanceof Block)){
        continue;
      }
      $packets[] = UpdateBlockPacket::create(BlockPosition::fromVector3($b->getPosition()), RuntimeBlockMapping::getInstance()->toRuntimeId($b->getFullId()), UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL);
    }

    foreach($realBlocks as $b){
      if(!($b instanceof Vector3)){
        throw new \TypeError("Expected Vector3 in blocks array, got " . (is_object($b) ? get_class($b) : gettype($b)));
      }

      $fullBlock = $world->getBlockAt($b->x, $b->y, $b->z);
      $packets[] = UpdateBlockPacket::create(BlockPosition::fromVector3($b), RuntimeBlockMapping::getInstance()->toRuntimeId($fullBlock->getFullId()), UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL);

      $tile = $world->getTileAt($b->x, $b->y, $b->z);
      if($tile instanceof Spawnable){
        $packets[] = BlockActorDataPacket::create(BlockPosition::fromVector3($b), $tile->getSerializedSpawnCompound());
      }
    }

    Server::getInstance()->broadcastPackets($targets, $packets);
  }

  public function executeCPPCode(string $file, bool $compile = false, int $taskId = 0, bool $async = false): ?string{
    if($compile){
      if(!$async){
        if(file_exists($this->getDataFolder()."resources/compiled_scripts/".$file.".blka")){
          unlink($this->getDataFolder()."resources/compiled_scripts/".$file.".blka");
        }
        if(file_exists($this->getDataFolder()."resources/scripts/".$file.".cpp")){
          $output = $this->getDataFolder()."resources/compiled_scripts/".$file.".blka";
          exec("g++ ".$this->getDataFolder()."resources/scripts/".$file.".cpp"." -o ".$output);
          exec("chmod 0777 ".$output);
        }else{
          return null;
        }
      }else{
        //todo
      }
    }
    if(file_exists($this->getDataFolder()."resources/compiled_scripts/".$file.".blka")){
      if(!$async){
        return exec($this->getDataFolder()."resources/compiled_scripts/".$file.".blka");
      }else{
        //todo
      }
    }
    return null;
  }

  public function removeOldScripts(): void{
    $startPath = $this->getDataFolder()."resources/compiled_scripts/";
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
        }elseif(pathinfo($path)["extension"] == "blka"){
          unlink($path);
          $usedPaths[] = $path;
        }
      }
      $previousPath = $currentPath;
    }
  }

  public function compileScripts(): void{
    $startPath = $this->getDataFolder()."resources/scripts/";
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
        }elseif(pathinfo($path)["extension"] == "cpp"){
          $nPath = str_replace("resources/scripts/", "resources/compiled_scripts/", $currentPath);
          $output = $nPath.$file.".blka";
          exec("g++ ".$path." -o ".$output);
          exec("chmod 0777 ".$output);
          $usedPaths[] = $path;
        }
      }
      $previousPath = $currentPath;
    }
  }
}