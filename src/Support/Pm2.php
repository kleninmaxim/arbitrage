<?php

namespace Src\Support;

/**
 * @method static void stopByName(string $name)
 * @method static void stopByNamespace(string $namespace)
 * @method static void stopAll()
 * @method static void deleteAll()
 */
class Pm2
{
    public static function start(string $file, string $name, string $namespace, array $arguments = [], bool $force = false, bool $is_output = false, bool $is_error = false): mixed
    {
        $pm2_command = 'pm2 start ' . $file . ' --name "' . $name . '"  --namespace "' . $namespace . '"';

        if (!$is_output)
            $pm2_command .= ' -o /dev/null';

        if (!$is_error)
            $pm2_command .= ' -e /dev/null';

        $pm2_command .= ' -m';

        if ($force)
            $pm2_command .= ' -f';

        if ($arguments)
            $pm2_command .= ' -- "' . implode('" "', $arguments) . '"';

        return Exec::exec($pm2_command);
    }

    public static function list(): array
    {
        return array_filter(
            array_map(
                function ($process) {
                    $process_as_array = explode('│', trim($process, '│'));

                    if (count($process_as_array) == 13) {
                        $process_as_array = array_map(fn($process) => trim($process), $process_as_array);

                        return [
                            'id' => $process_as_array[0],
                            'name' => trim($process_as_array[1]),
                            'namespace' => $process_as_array[2],
                            'uptime' => $process_as_array[6],
                            'restarts' => $process_as_array[7],
                            'status' => $process_as_array[8],
                            'memory' => $process_as_array[9],
                        ];
                    }

                    return false;
                },
                array_slice(Exec::exec('pm2 l', true), 3, -1)
            ),
            fn($item) => $item
        );
    }

    public static function __callStatic($method, $args): void
    {
        match($method) {
            'stopByName' => Exec::exec('pm2 stop "' . $args[0] . '"'),
            'startByName' => Exec::exec('pm2 start "' . $args[0] . '"'),
            'stopByNamespace' => Exec::exec('pm2 stop ' . $args[0]),
            'stopAll' => Exec::exec('pm2 stop all'),
            'deleteAll' => Exec::exec('pm2 delete all'),
        };
    }
}