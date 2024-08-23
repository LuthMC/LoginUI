<?php

namespace Luthfi\LoginUI;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use Luthfi\LoginUI\libs\LootSpace369\LSFormAPI\SimpleForm;
use Luthfi\LoginUI\libs\LootSpace369\LSFormAPI\CustomForm;
use Luthfi\LoginUI\libs\LootSpace369\LSFormAPI\ModalForm;

class EventListener implements Listener {

    private $plugin;
    private $loginAttempts = [];
    private $playerDataManager;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->playerDataManager = new PlayerDataManager($plugin);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
    $player = $event->getPlayer();
    $this->handlePlayerJoin($player);
    }

    private function sendRegisterForm(Player $player): void {
    $form = new CustomForm("Register", function (Player $player, ?array $data) {
        if ($data === null) return;

        $username = $data[0];
        $password = $data[1];
        $pin = $this->plugin->getConfig()->get("enable-pin") ? $data[2] : null;
        $this->playerDataManager->registerPlayer($player, $password, $pin);
        $player->sendMessage($this->plugin->getConfig()->get("messages")["register-success"]);

        if ($pin !== null) {
            $this->sendPinConfirmationForm($player);
        }
    });

    $form->addInput("Username:");
    $form->addInput("Password:");

    if ($this->plugin->getConfig()->get("enable-pin")) {
        $form->addInput("PIN:");
    }
    $player->sendForm($form);
 }

    private function sendLoginForm(Player $player): void {
    if (!isset($this->loginAttempts[$player->getName()])) {
        $this->loginAttempts[$player->getName()] = 0;
    }

    $form = new CustomForm("Login", function (Player $player, ?array $data) {
        if ($data === null) return;

        $username = $data[0];
        $password = $data[1];

        if ($this->playerDataManager->validateLogin($player, $password)) {
            $player->sendMessage($this->plugin->getConfig()->get("messages")["login-success"]);
            unset($this->loginAttempts[$player->getName()]);
        } else {
            $this->loginAttempts[$player->getName()]++;
            if ($this->loginAttempts[$player->getName()] >= $this->plugin->getConfig()->get("max-login-attempts")) {
                $player->sendMessage($this->plugin->getConfig()->get("messages")["max-attempts"]);
                $player->kick($this->plugin->getConfig()->get("messages")["max-attempts"], false);
            } else {
                $player->sendMessage($this->plugin->getConfig()->get("messages")["login-fail"]);
                $this->sendLoginForm($player);
            }
        }
    });

    $form->addInput("Username:");
    $form->addInput("Password:");

    $player->sendForm($form);
}

    private function sendPinConfirmationForm(Player $player): void {
        $form = new ModalForm("Confirmation", "Are you sure you want to proceed?", "Yes", "No", function (Player $player, ?bool $data) {
            if ($data === null) return;

            if ($data) {
                $player->sendMessage("Account Saved!");
            } else {
                $player->sendMessage("Account Not Saved!");
            }
        });

        $player->sendForm($form);
    }

    private function handlePlayerJoin(Player $player): void {
    $data = yaml_parse_file($this->dataFile);
    $username = strtolower($player->getName());

    if (isset($data[$username])) {
        $this->sendLoginForm($player);
    } else {
        $this->sendRegisterForm($player);
        }
    }
}
