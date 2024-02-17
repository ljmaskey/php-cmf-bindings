<?php

declare(strict_types=1);

namespace ljmaskey\TestTrait\CmfBindings;

trait ListByteStreamTestTrait
{
    /**
     * @param int<0, 255>[] $bytes
     *
     * @return resource $resource
     */
    private function getByteStreamFromList(array $bytes): mixed
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        // Write the bytes to the stream.
        foreach ($bytes as $inputByte) {
            fputs($stream, pack('C', $inputByte), 1);
        }

        rewind($stream);

        return $stream;
    }
}
