<?php

namespace Luthfi\LoginUI;

use pocketmine\plugin\PluginBase;

class Main extends PluginBase {

    private static $instance;

    public static function getInstance(): Main {
        return self::$instance;
    }

    public function onEnable(): void {
        self::$instance = $this;
        $this->saveDefaultConfig();

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->getLogger()->info("LoginUI Enabled!");
    }

    public function onDisable(): void {
        $this->getLogger()->info("LoginUI Disabled!");
    }
}
