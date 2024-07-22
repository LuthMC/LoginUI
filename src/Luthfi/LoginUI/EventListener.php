<?php

declare(strict_types=1);

namespace Luthfi\LoginUI;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

class EventListener implements Listener
{
    /** @var Main */
    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        if ($this->plugin->getPlayerData()->exists($player->getName())) {
            $this->plugin->showLoginForm($player);
        } else {
            $this->plugin->showRegisterOrLoginForm($player);
        }
    }
}
