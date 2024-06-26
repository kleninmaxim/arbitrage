<?php

namespace Src\Support;

use Exception;

class Log
{
    public static bool $debug = false;
    private static string $path;
    private static string $current_project_path;

    public static function initPath(string $path, int $level = 2): void
    {
        self::$path = $path;
        self::$current_project_path = dirname(__DIR__, $level);
    }

    public static function log(string $file_name, array|string $text): void
    {
        $date = date('Y-m-d H:i:s');

        if (is_array($text)) {
            $data = print_r($text, true);

            $content = <<<EOT
                ------------------------------------------------------------------------------------------------------------------------
                [$date]
                $data
                ------------------------------------------------------------------------------------------------------------------------
                EOT;
        } else
            $content = '[' . $date . '] ' . $text . "\n";

        file_put_contents(
            self::$path . str_replace('/', '-', $file_name) . '.log',
            $content,
            FILE_APPEND
        );
    }

    public static function debug(array|string $text): void
    {
        if (self::$debug)
            self::log('debug', $text);
    }

    public static function warning(array|string $text): void
    {
        self::log('warning', $text);
    }

    public static function error(Exception $e, mixed $record = null): void
    {
        $time = date('Y-m-d H:i:s');
        $message = $e->getMessage();
        $error_place = str_replace(self::$current_project_path, '.', $e->getFile()) . ' ' . $e->getLine();
        $errors = implode(
            "\n",
            array_map(
                fn($trace) => str_replace(self::$current_project_path, '.', ($trace['file'] ?? 'no_file')) . ' ' . ($trace['line'] ?? 'no_line') . ' ' . ($trace['class'] ?? 'no_class') . ($trace['type'] ?? 'no_type') . ($trace['function'] ?? 'no_function'),
                $e->getTrace()
            )
        );

        if ($record)
            $errors .= "\n\n" . print_r($record, true);

        file_put_contents(
            self::$path . 'error' . '.log',
            <<<EOT
            ------------------------------------------------------------------------------------------------------------------------
            [$time] $message
            $error_place
            $errors
            ------------------------------------------------------------------------------------------------------------------------
            EOT . "\n",
            FILE_APPEND
        );
    }
}