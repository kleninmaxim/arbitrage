<?php

namespace Src\Crypto\Exchanges;

use Exception;

trait HasStreams
{
    /**
     * @throws Exception
     */
    public function getStreams(array $all_streams): array
    {
        foreach ($all_streams as $stream_name => $options) {
            foreach ($options as $option) {
                $streams[] = $this->getStream($stream_name, $option);
            }
        }

        return $streams ?? [];
    }

    /**
     * @throws Exception
     */
    public function getStreamsWithOptions(array $all_streams): array
    {
        foreach ($all_streams as $stream_name => $options) {
            foreach ($options as $option) {
                $streams[$this->getStream($stream_name, $option)] = [
                    'stream_name' => $stream_name,
                    'options' => $option,
                ];
            }
        }

        return $streams ?? [];
    }
}