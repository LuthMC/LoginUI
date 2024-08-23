<?php

namespace Luthfi\LoginUI;

use pocketmine\player\Player;

class PlayerDataManager {

    private $plugin;
    private $dataFile;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->dataFile = $this->plugin->getDataFolder() . "players.yml";
        if (!file_exists($this->dataFile)) {
            file_put_contents($this->dataFile, yaml_emit([]));
        }
    }

    public function registerPlayer(Player $player, string $password, ?string $pin): void {
    $data = yaml_parse_file($this->dataFile);
    $username = strtolower($player->getName());

    $data[$username] = [
        "password" => $password,
        "pin" => $pin
    ];

    file_put_contents($this->dataFile, yaml_emit($data));
  }

    public function validateLogin(Player $player, string $password): bool {
    $data = yaml_parse_file($this->dataFile);
    $username = strtolower($player->getName());

    if (!isset($data[$username])) {
        return false;
    }

    return $password === $data[$username]["password"];
  }

    public function validatePin(Player $player, string $pin): bool {
        $data = yaml_parse_file($this->dataFile);
        $username = strtolower($player->getName());

        if (!isset($data[$username]) || $data[$username]["pin"] === null) {
            return false;
        }

        return password_verify($pin, $data[$username]["pin"]);
    }

    private function logLoginAttempt(string $username, bool $success): void {
    $data = yaml_parse_file($this->dataFile);

    if (!isset($data[$username]["login_attempts"])) {
        $data[$username]["login_attempts"] = [];
    }

    $data[$username]["login_attempts"][] = [
        "timestamp" => time(),
        "success" => $success
    ];

    if (count($data[$username]["login_attempts"]) > 10) {
        $data[$username]["login_attempts"] = array_slice($data[$username]["login_attempts"], -10);
    }

    file_put_contents($this->dataFile, yaml_emit($data));
    }
}
