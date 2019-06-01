<?php


namespace nlog\management;

use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginLoader;
use pocketmine\Server;

class PluginCustomLoader implements PluginLoader {

    public function canLoadPlugin(string $path): bool {
        $name = str_replace(Server::getInstance()->getPluginPath(), '', $path);
        return ManagementLoader::isValidPlugin($name);
    }

    public function loadPlugin(string $file): void {
    }

    public function getPluginDescription(string $file): ?PluginDescription {
        if (is_dir($file) and file_exists($file . "/plugin.yml")) {
            $yaml = @file_get_contents($file . "/plugin.yml");
            if ($yaml != "") {
                return new PluginDescription($yaml);
            }
        }

        return null;
    }

    public function getAccessProtocol(): string {
        return "";
    }

}