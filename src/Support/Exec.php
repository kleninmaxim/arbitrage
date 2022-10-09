<?php

namespace Src\Support;

class Exec
{
    public static function exec(string $command, bool $return_output = false): mixed
    {
        exec($command, $output, $code);

        if ($code == 0)
            return ($return_output) ? $output : true;

        return false;
    }
}