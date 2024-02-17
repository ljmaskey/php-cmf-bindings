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

use ljmaskey\CmfBindings\Enum\ValueType;
use ljmaskey\CmfBindings\Exception\SerializationException;
use ljmaskey\CmfBindings\Trait\WritesDataTrait;

/** @phpstan-import-type ByteData from WritesDataTrait */
class MessageBuilder
{
    use WritesDataTrait;

    /**
     * Note that our $resource parameter is only mixed until we can explicitly specify it as array|resource.
     *
     * @TODO: Once we can explicitly define our parameter as array|resource, change this parameter type.
     *
     * @param ByteData         $resource
     * @param non-negative-int $position
     */
    public function __construct(private mixed &$resource, private int $position)
    {
        $this->ensureDataAndPositionParametersAreValid($this->resource, $this->position);
    }

    /** @return non-negative-int */
    public function getPosition(): int
    {
        return $this->position;
    }

    public function addInt(int $tag, int $value): void
    {
        $valueType = ValueType::PositiveNumber;
        if ($value < 0) {
            $valueType = ValueType::NegativeNumber;
            $value = abs($value);
        }
        $this->write($tag, $valueType);

        $varInt = new VarInt($this->resource, $this->position);
        $this->position += $varInt->serialize($value);
    }

    public function addString(int $tag, string $value): void
    {
        $this->write($tag, ValueType::String);

        $serializedData = unpack('C*', $value);
        assert(is_array($serializedData));

        $varInt = new VarInt($this->resource, $this->position);
        $this->position += $varInt->serialize(count($serializedData));

        foreach ($serializedData as $serializedByte) {
            $this->addByteToData($this->resource, $serializedByte);
            $this->position++;
        }
    }

    /**
     * We say it's an array of strings and ints, but it's actually an array of chars and / or bytes. We will ensure that
     * before we write the values to the stream.
     *
     * @param array<string|int> $value
     */
    public function addByteArray(int $tag, array $value): void
    {
        $this->write($tag, ValueType::ByteArray);

        $varInt = new VarInt($this->resource, $this->position);
        $this->position += $varInt->serialize(count($value));

        foreach ($value as $byte) {
            $byte = $this->inputByteToOutputByte($byte, SerializationException::class);
            $this->addByteToData($this->resource, $byte);
            $this->position++;
        }
    }

    public function addBoolean(int $tag, bool $value): void
    {
        $this->write($tag, $value ? ValueType::BoolTrue : ValueType::BoolFalse);
    }

    public function addDouble(int $tag, float $value): void
    {
        $this->write($tag, ValueType::Double);

        /*
         * Using the 'e' format gives us a little-endian double, but the size is machine dependent. Our composer package
         * requires 64-bit PHP so this should be 8 bytes (but we will confirm it, just in case).
         */
        $packedBytes = pack('e', $value);
        $packedBytesArray = str_split($packedBytes);
        assert(count($packedBytesArray) == 8);

        foreach ($packedBytesArray as $byte) {
            $byte = ord($byte);
            $this->addByteToData($this->resource, $byte);
            $this->position++;
        }
    }

    private function write(int $tag, ValueType $type): void
    {
        // The tag originally came from outside this package. Unless we want to check every spot where it is given to
        // us, we will just check it here.
        if ($tag < 0 || $tag > 65535) {
            throw new \InvalidArgumentException('Invalid tag: '.$tag);
        }

        if ($tag >= 31) { // use more than 1 byte
            $byte = ($type->value | 0xF8); // set the 'tag' to all 1s
            $this->addByteToData($this->resource, $byte);
            $this->position++;
            $varInt = new VarInt($this->resource, $this->position);
            $this->position += $varInt->serialize($tag);

            return;
        }

        $byte = $tag;
        $byte = ($byte << 3);
        $byte += $type->value;

        // Mathematically, this shouldn't happen until the $type->value gets to 16. These asserts are just here to help
        // with static analysis.
        assert($byte >= 0);
        assert($byte <= 255);

        $this->addByteToData($this->resource, $byte);
        $this->position++;
    }
}
