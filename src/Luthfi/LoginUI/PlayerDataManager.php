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

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $hashedPin = $pin !== null ? password_hash($pin, PASSWORD_BCRYPT) : null;

        $data[$username] = [
            "password" => $hashedPassword,
            "pin" => $hashedPin
        ];

        file_put_contents($this->dataFile, yaml_emit($data));
    }

    public function validateLogin(Player $player, string $password): bool {
        $data = yaml_parse_file($this->dataFile);
        $username = strtolower($player->getName());

        if (!isset($data[$username])) {
            return false;
        }

        return password_verify($password, $data[$username]["password"]);
    }

    public function validatePin(Player $player, string $pin): bool {
        $data = yaml_parse_file($this->dataFile);
        $username = strtolower($player->getName());

        if (!isset($data[$username]) || $data[$username]["pin"] === null) {
            return false;
        }

        return password_verify($pin, $data[$username]["pin"]);
    }
}
