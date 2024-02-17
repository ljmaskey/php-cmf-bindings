<?php
/*
 * Copyright (c) 2024 Lincoln Maskey
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace ljmaskey\CmfBindings\Trait;

use ljmaskey\CmfBindings\Exception\InternalPackageException;
use ljmaskey\CmfBindings\Exception\UnserializationException;

/**
 * Because the incoming data comes from outside the package, we cannot guarantee that it is ByteData.
 *
 * @phpstan-import-type InputByte from InteractsWithDataTrait
 * @phpstan-import-type OutputByte from InteractsWithDataTrait
 * @phpstan-import-type ByteData from InteractsWithDataTrait
 */
trait ReadsDataTrait
{
    use InteractsWithDataTrait;

    /**
     * @param ByteData         $data
     * @param non-negative-int $position
     * @param non-negative-int $numberOfBytesToRead
     *
     * @return list<OutputByte>
     */
    private function readBytesFromData(mixed &$data, int $position, int $numberOfBytesToRead): array
    {
        if ($numberOfBytesToRead < 0) {
            throw new InternalPackageException('Given number of bytes to read is negative');
        }

        // Read our bytes individually.
        $readBytes = [];

        for ($i = 0; $i < $numberOfBytesToRead; $i++) {
            $readBytes[] = $this->readByteFromData($data, $position);
            $position++;
        }

        return $readBytes;
    }

    /**
     * By the time we get to this method, if we are working with a list of InputBytes, we should have already ensured
     * that the bytes are valid. Therefore, we will throw an InternalPackageException if we find one that isn't. We may
     * still go past the end of bytes in the array (if only to align with the fact that we may do the same with a
     * stream) so we treat running out of bytes as an UnserializationException.
     *
     * @param ByteData         $data
     * @param non-negative-int $position
     *
     * @return OutputByte
     */
    private function readByteFromData(mixed &$data, int $position): int
    {
        if (is_array($data)) {
            if ($position >= count($data)) {
                throw new UnserializationException('Reading byte for '.__CLASS__.' past array data');
            }

            $byte = $this->inputByteToOutputByte($data[$position], InternalPackageException::class);

            return $byte;
        }

        return $this->readNextByteFromResource($data);
    }

    /**
     * @param resource $resource
     *
     * @return OutputByte
     *
     * @TODO: We may want to support skipping to a new position in the resource.
     */
    private function readNextByteFromResource(mixed $resource): int
    {
        if (!is_resource($resource)) {
            throw new InternalPackageException('Given parameter is not a resource');
        }

        // At least try and read the next byte, but then ensure that we were able to.
        $readChar = fread($resource, 1);

        if (feof($resource)) {
            throw new UnserializationException('Reached end of resource');
        }

        /*
         * According to https://stackoverflow.com/a/24467339, at least, fread only returns false if the first argument
         * is an invalid resource (which we have already checked) or the length is less than or equal to zero (which we
         * know is not true). This assertion is only for static analysis.
         */
        assert(is_string($readChar));

        // Unpack the char that we just read into an 'array' of actual bytes.
        $unpackedByteArray = unpack('C', $readChar);
        /*
         * We know that we read a (single) byte above, so we know that we will have something in $readChar, and
         * therefore we know that $unpackedByteArray will always be an array of length 1. We are asserting that here,
         * though, to help with static analysis.
         */
        assert(is_array($unpackedByteArray));
        assert(count($unpackedByteArray) == 1);

        // Get the one binary string that we expect.
        $nextByte = array_shift($unpackedByteArray);

        return $nextByte;
    }
}
