<?php

namespace Luthfi\LoginUI;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\command;
use pocketmine\command/CommandSender;
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
    if ($command->getName() === "profile") {
        if ($sender instanceof Player) {
            $this->sendProfileForm($sender);
        } else {
            $sender->sendMessage("This command can only be used in-game.");
        }
        return true;
    }

    if ($command->getName() === "resetpassword") {
        if ($sender instanceof Player) {
            $this->sendResetPasswordForm($sender);
        } else {
            $sender->sendMessage("This command can only be used in-game.");
        }
        return true;
    }

    $username = strtolower($args[0] ?? '');

    if ($command->getName() === "adminresetpassword") {
        if (!$sender->hasPermission("login.arp")) {
            $sender->sendMessage("You don't have permission to use this command.");
            return true;
        }

        if (empty($username)) {
            $sender->sendMessage("Please specify a username.");
            return true;
        }

        $this->sendAdminResetPasswordForm($sender, $username);
        return true;
    }

    if ($command->getName() === "deleteaccount") {
        if (!$sender->hasPermission("login.da")) {
            $sender->sendMessage("You don't have permission to use this command.");
            return true;
        }

        if (empty($username)) {
            $sender->sendMessage("Please specify a username.");
            return true;
        }

        $this->playerDataManager->deletePlayerAccount($username);
        $sender->sendMessage("Account deleted successfully.");
        return true;
    }

    if ($command->getName() === "viewlogin") {
        if (!$sender->hasPermission("login.vl")) {
            $sender->sendMessage("You don't have permission to use this command.");
            return true;
        }

        $this->playerDataManager->viewPlayerLoginAttempts($username);
        return true;
    }

    return false;
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

    private function sendProfileForm(Player $player): void {
    $form = new CustomForm(function (Player $player, ?array $data) {
        if ($data === null) return;

        $newPassword = $data[0];
        $newPin = $this->plugin->getConfig()->get("enable-pin") ? $data[1] : null;

        $this->updatePlayerProfile($player, $newPassword, $newPin);

        $player->sendMessage($this->plugin->getConfig()->get("messages")["profile-update-success"]);
    });

    $form->setTitle("Update Profile");
    $form->addInput("New Password:");
    if ($this->plugin->getConfig()->get("enable-pin")) {
        $form->addInput("New PIN:");
    }

    $player->sendForm($form);
    }
    
    private function sendResetPasswordForm(Player $player): void {
    $form = new CustomForm(function (Player $player, ?array $data) {
        if ($data === null) return;

        $newPassword = $data[0];
        $newPin = $this->plugin->getConfig()->get("enable-pin") ? $data[1] : null;

        $this->resetPlayerPassword($player, $newPassword, $newPin);

        $player->sendMessage($this->plugin->getConfig()->get("messages")["reset-success"]);
    });

    $form->setTitle("Reset Password");
    $form->addInput("New Password:");
    if ($this->plugin->getConfig()->get("enable-pin")) {
        $form->addInput("New PIN:");
    }

    $player->sendForm($form);
    }
    
    private function sendAdminResetPasswordForm(CommandSender $sender, string $username): void {
    if (!$sender instanceof Player) return;

    $form = new CustomForm(function (Player $player, ?array $data) use ($username) {
        if ($data === null) return;

        $newPassword = $data[0];
        $newPin = $this->plugin->getConfig()->get("enable-pin") ? $data[1] : null;

        $this->resetPlayerPasswordByAdmin($username, $newPassword, $newPin);

        $player->sendMessage($this->plugin->getConfig()->get("messages")["admin-reset-success"]);
    });

    $form->setTitle("Admin Reset Password");
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

   public function viewPlayerLoginAttempts(CommandSender $sender, string $username): void {
    $data = yaml_parse_file($this->dataFile);

    if (!isset($data[$username])) {
        $sender->sendMessage("No data found for this player.");
        return;
    }

    if (!isset($data[$username]["login_attempts"])) {
        $sender->sendMessage("No login attempts recorded for this player.");
        return;
    }

    $sender->sendMessage("Login attempts for " . $username . ":");
    foreach ($data[$username]["login_attempts"] as $attempt) {
        $dateTime = date("Y-m-d H:i:s", $attempt["timestamp"]);
        $status = $attempt["success"] ? "Success" : "Failed";
        $sender->sendMessage("- [$dateTime] $status");
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
