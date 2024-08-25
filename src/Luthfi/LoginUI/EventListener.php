<?php

namespace Luthfi\LoginUI;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use Luthfi\LoginUI\libs\LootSpace369\LSFormAPI\SimpleForm;
use Luthfi\LoginUI\libs\LootSpace369\LSFormAPI\CustomForm;
use Luthfi\LoginUI\libs\LootSpace369\LSFormAPI\ModalForm;

class EventListener implements Listener {

    private $plugin;
    private $loginAttempts = [];
    private $playerDataManager;
    private $dataFile;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->playerDataManager = new PlayerDataManager($plugin);
        $this->dataFile = $plugin->getDataFolder() . "players.yml";
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
    $player = $event->getPlayer();
    $this->handlePlayerJoin($player);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
    if (!$sender instanceof Player) {
        $sender->sendMessage("This command can only be used in-game.");
        return false;
    }

    switch ($command->getName()) {
        case "profile":
            $this->sendProfileForm($sender);
            break;
        case "resetpassword":
            $this->sendResetPasswordForm($sender);
            break;
        case "adminresetpassword":
            if (isset($args[0])) {
                $this->sendAdminResetPasswordForm($sender, $args[0]);
            } else {
                $sender->sendMessage("Please provide a username.");
            }
            break;
        case "deleteaccount":
            if ($this->playerDataManager->deleteAccount($sender)) {
                $sender->sendMessage("Account has been successfully deleted.");
            } else {
                $sender->sendMessage("No account found to delete.");
            }
            break;
        default:
            return false;
    }
    return true;
 }
    
    private function sendRegisterForm(Player $player): void {
    $form = new CustomForm("Register", function (Player $player, ?array $data) {
        if ($data === null) {
            $player->kick("You must register to play on this server.");
            return;
        }

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

    private function sendProfileForm(Player $player): void {
    $form = new CustomForm("Update Profile", function (Player $player, ?array $data) {
        if ($data === null) return;

        $newPassword = $data[0];
        $newPin = $this->plugin->getConfig()->get("enable-pin") ? $data[1] : null;

        $this->updatePlayerProfile($player, $newPassword, $newPin);

        $player->sendMessage($this->plugin->getConfig()->get("messages")["profile-update-success"]);
    });

    $form->addInput("New Password:");
    if ($this->plugin->getConfig()->get("enable-pin")) {
        $form->addInput("New PIN:");
    }

    $player->sendForm($form);
 }
    
    private function sendResetPasswordForm(Player $player): void {
    $form = new CustomForm("Reset Password", function (Player $player, ?array $data) {
        if ($data === null) return;

        $newPassword = $data[0];
        $newPin = $this->plugin->getConfig()->get("enable-pin") ? $data[1] : null;

        $this->resetPlayerPassword($player, $newPassword, $newPin);

        $player->sendMessage($this->plugin->getConfig()->get("messages")["reset-success"]);
    });

    $form->addInput("New Password:");
    if ($this->plugin->getConfig()->get("enable-pin")) {
        $form->addInput("New PIN:");
    }

    $player->sendForm($form);
 }
    
    private function sendAdminResetPasswordForm(CommandSender $sender, string $username): void {
    if (!$sender instanceof Player) return;

    $form = new CustomForm("Admin Reset Password", function (Player $player, ?array $data) use ($username) {
        if ($data === null) return;

        $newPassword = $data[0];
        $newPin = $this->plugin->getConfig()->get("enable-pin") ? $data[1] : null;

        $this->resetPlayerPasswordByAdmin($username, $newPassword, $newPin);

        $player->sendMessage($this->plugin->getConfig()->get("messages")["admin-reset-success"]);
    });

    $form->addInput("New Password:");
    if ($this->plugin->getConfig()->get("enable-pin")) {
        $form->addInput("New PIN:");
    }

    $sender->sendForm($form);
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

   public function updatePlayerProfile(Player $player, string $newPassword, ?string $newPin): void {
    $data = yaml_parse_file($this->dataFile);
    $username = strtolower($player->getName());

    $data[$username]["password"] = $newPassword;
    if ($newPin !== null) {
        $data[$username]["pin"] = $newPin;
    }

    file_put_contents($this->dataFile, yaml_emit($data));
   }

   public function resetPlayerPassword(Player $player, string $newPassword, ?string $newPin): void {
    $data = yaml_parse_file($this->dataFile);
    $username = strtolower($player->getName());

    $data[$username]["password"] = $newPassword;
    if ($newPin !== null) {
        $data[$username]["pin"] = $newPin;
    }

    file_put_contents($this->dataFile, yaml_emit($data));
   }

   public function resetPlayerPasswordByAdmin(string $username, string $newPassword, ?string $newPin): void {
    $data = yaml_parse_file($this->dataFile);

    $data[$username]["password"] = $newPassword;
    if ($newPin !== null) {
        $data[$username]["pin"] = $newPin;
    }

    file_put_contents($this->dataFile, yaml_emit($data));
   }

   public function deletePlayerAccount(string $username): void {
    $data = yaml_parse_file($this->dataFile);

    if (isset($data[$username])) {
        unset($data[$username]);
        file_put_contents($this->dataFile, yaml_emit($data));
      }
   }
}
