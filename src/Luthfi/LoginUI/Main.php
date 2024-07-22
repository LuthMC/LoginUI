<?php

declare(strict_types=1);

namespace Luthfi\LoginUI;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use Luthfi\LoginUI\EventListener;
use LootSpace369\lsform\CustomForm;
use LootSpace369\lsform\SimpleForm;

class Main extends PluginBase
{
    /** @var Config */
    private $playerData;

    /** @var Config */
    private $attemptsData;

    public function onEnable(): void
    {
        $this->saveDefaultConfig();
        @mkdir($this->getDataFolder() . "players/");
        $this->playerData = new Config($this->getDataFolder() . "players/players.yml", Config::YAML);
        $this->attemptsData = new Config($this->getDataFolder() . "players/attempts.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    public function getPlayerData(): Config
    {
        return $this->playerData;
    }

    public function getAttemptsData(): Config
    {
        return $this->attemptsData;
    }

    public function showRegisterOrLoginForm(Player $player): void
    {
        $form = $this->getServer()->getPluginManager()->getPlugin("LSFormAPI")->createSimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    $this->showRegisterForm($player);
                    break;
                case 1:
                    $this->showLoginForm($player);
                    break;
            }
        });
        $form->setTitle("§l§bWelcome!");
        $form->setContent("§7Please register or log in.");
        $form->addButton("§bRegister");
        $form->addButton("§3I already registered");
        $form->sendToPlayer($player);
    }

    public function showRegisterForm(Player $player): void
    {
        $form = $this->getServer()->getPluginManager()->getPlugin("LSFormAPI")->createCustomForm(function (Player $player, array $data = null) {
            if ($data === null) {
                return;
            }
            $username = $data[0];
            $password = $data[1];
            $playerName = $player->getName();

            $playerData = $this->getPlayerData();
            $attemptsData = $this->getAttemptsData();

            if ($playerData->exists($playerName)) {
                $this->showErrorForm($player, "§cYou are already registered.", "§cAlready Registered");
                return;
            }

            if ($playerData->exists($username)) {
                $this->showErrorForm($player, "§cUsername already used. Try another one.", "§cUsername Already Used");
                return;
            }

            $playerData->set($playerName, ["username" => $username, "password" => $password]);
            $playerData->save();
            $attemptsData->remove($playerName);
            $attemptsData->save();
            $player->sendMessage("§aRegistered successfully!");
        });

        $form->setTitle("§b§lRegister");
        $form->addInput("Username");
        $form->addInput("Password");
        $form->sendToPlayer($player);
    }

    public function showLoginForm(Player $player): void
    {
        $form = $this->getServer()->getPluginManager()->getPlugin("LSFormAPI")->createCustomForm(function (Player $player, array $data = null) {
            if ($data === null) {
                return;
            }
            $username = $data[0];
            $password = $data[1];
            $playerName = $player->getName();

            $playerData = $this->getPlayerData();
            $attemptsData = $this->getAttemptsData();

            if (!$playerData->exists($playerName)) {
                $this->showErrorForm($player, "§cYou are not registered. Please register first.", "§cNot Registered");
                return;
            }

            $playerData = $playerData->get($playerName);
            if ($playerData["username"] === $username && $playerData["password"] === $password) {
                $player->sendMessage("§aLogged in successfully!");
                $attemptsData->remove($playerName);
                $attemptsData->save();
            } else {
                $attempts = $attemptsData->get($playerName, 0) + 1;
                if ($attempts >= 3) {
                    $player->kick("§cToo many failed login attempts.");
                    $attemptsData->remove($playerName);
                } else {
                    $this->showErrorForm($player, "§cIncorrect username or password. Attempts remaining: " . (3 - $attempts), "§cLogin Failed");
                    $attemptsData->set($playerName, $attempts);
                    $attemptsData->save();
                }
            }
        });

        $form->setTitle("§l§bLogin");
        $form->addInput("Username");
        $form->addInput("Password");
        $form->sendToPlayer($player);
    }

    private function showErrorForm(Player $player, string $content, string $title): void
    {
        $form = $this->getServer()->getPluginManager()->getPlugin("LSFormAPI")->createSimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                return;
            }
            $this->showRegisterForm($player);
        });
        $form->setTitle($title);
        $form->setContent($content);
        $form->addButton("Retry");
        $form->sendToPlayer($player);
    }
}
