<?php

namespace Src\Support;

class Config
{
    private static string $path;
    private static array $configs = [];
    private static string $default_settings_file = 'app';

    public static function initPath(string $path): void
    {
        self::$path = $path;
    }

    public static function get(string $file): mixed
    {
        if (!isset(self::$configs[$file]))
            self::$configs[$file] = require_once self::$path . $file . '.config.php';

        return self::$configs[$file];
    }

    public static function file(string $config_file, string $name, string $setting_name = null)
    {
        return self::config($config_file, $name, $setting_name ?: self::config(self::$default_settings_file, $name));
    }

    public static function config(string $file, string ...$keys)
    {
        $config = self::get($file);

        if ($keys)
            foreach ($keys as $key)
                $config = $config[$key];

        return $config;
    }

    public static function changeDefaultSettingsFile(string $default_settings_file): void
    {
        self::$default_settings_file = $default_settings_file;
    }

    public static function getDefaultSettingsFile(): string
    {
        return self::$default_settings_file;
    }
}