<?php
/*
 * Copyright (c) 2016 Tom Zander <tomz@freedommail.ch>
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

namespace ljmaskey\CmfBindings;

use ljmaskey\CmfBindings\Exception\SerializationException;
use ljmaskey\CmfBindings\Exception\UnserializationException;
use ljmaskey\CmfBindings\Trait\ReadsDataTrait;
use ljmaskey\CmfBindings\Trait\WritesDataTrait;

/**
 * @phpstan-import-type OutputByte from ReadsDataTrait
 * @phpstan-import-type ByteData from ReadsDataTrait
 */
final class VarInt
{
    use ReadsDataTrait;
    use WritesDataTrait;

    public const MAXIMUM_BYTE_LENGTH = 8;
    public const MAXIMUM_ALLOWED_ENCODABLE_NUMBER = 72_624_976_668_147_839;

    /**
     * Note that our $data parameter is only mixed until we can explicitly specify it as array|resource.
     *
     * @param ByteData         $data
     * @param non-negative-int $position
     */
    public function __construct(private mixed &$data, private int $position)
    {
        $this->ensureDataAndPositionParametersAreValid($this->data, $this->position);
    }

    /** @return non-negative-int */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Append the serialized version of the value to the data block and return the number of bytes used.
     *
     * @param non-negative-int $value
     *
     * @return positive-int
     */
    public function serialize(int $value): int
    {
        if ($value < 0) {
            throw new SerializationException('Given value must be non-negative');
        }

        if ($value > self::MAXIMUM_ALLOWED_ENCODABLE_NUMBER) {
            throw new SerializationException('Given value to serialize exceeds VarInt limit');
        }

        /** @var list<OutputByte> $serializedBytes */
        $serializedBytes = [];
        $position = 0;

        while (true) {
            $step1 = $value & 0x7F;
            $step2 = ($position != 0) ? 0x80 : 0x00;

            // These two lines are only for static analysis.
            assert(($step1 | $step2) <= 255);
            assert(($step1 | $step2) >= 0);

            $serializedBytes[] = $step1 | $step2;

            if ($value <= 0x7F) {
                break;
            }

            $value = ($value >> 7) - 1;
            $position++;
        }

        $serializedBytes = array_reverse($serializedBytes);

        foreach ($serializedBytes as $serializedByte) {
            $this->addByteToData($this->data, $serializedByte);
            $this->position++;
        }

        return count($serializedBytes);
    }

    /** @return non-negative-int */
    public function unserialize(): int
    {
        $result = 0;
        $position = $this->position;

        // We have a maximum length of a VarInt. Because we might be reading this from a resource stream we can't
        // simply look at that the length of the data, so we will just read the next byte from the appropriate
        // source (ensuring that we were actually able to read from it) and return the number once we have found
        // the final byte.
        while (($position - $this->position) < self::MAXIMUM_BYTE_LENGTH) {
            $nextByte = $this->readByteFromData($this->data, $position);
            $position++;

            $result = ($result << 7) | ($nextByte & 0x7F);
            if (($nextByte & 0x80) != 0) {
                $result++;
            } else {
                $this->position = $position;

                // We assert here to help with static analysis.
                assert($result >= 0);

                return $result;
            }
        }

        throw new UnserializationException('Reading VarInt past maximum length');
    }
}
