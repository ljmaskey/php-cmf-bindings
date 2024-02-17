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

use ljmaskey\CmfBindings\Enum\State;
use ljmaskey\CmfBindings\Enum\ValueType;
use ljmaskey\CmfBindings\Exception\UnserializationException;
use ljmaskey\CmfBindings\Trait\ReadsDataTrait;

/**
 * @phpstan-import-type InputByte from ReadsDataTrait
 * @phpstan-import-type OutputByte from ReadsDataTrait
 * @phpstan-import-type ByteData from ReadsDataTrait
 */
class MessageParser
{
    use ReadsDataTrait;

    private int $endPosition;
    private int $tag = 0;
    private ?string $lastErrorMessage = null;
    /** @var list<OutputByte>|bool|int|float|null */
    private array|bool|int|float|null $value = null;

    /**
     * Note that our $resource parameter is only mixed until we can explicitly specify it as array|resource.
     *
     * @TODO: Once we can explicitly define our parameter as array|resource, change this parameter type.
     *
     * @param ByteData         $resource
     * @param non-negative-int $position
     * @param positive-int     $length
     */
    public function __construct(private mixed &$resource, private int $position, int $length)
    {
        $this->ensureDataAndPositionParametersAreValid($this->resource, $this->position);

        if ($length <= 0) {
            throw new \InvalidArgumentException('Given $length for MessageParser constructor must be a positive number');
        }

        $this->endPosition = $this->position + $length;

        if (is_array($this->resource) && (count($this->resource) < $this->endPosition)) {
            throw new \InvalidArgumentException('Given $resource array for MessageParser is shorter than the expected end position');
        }
    }

    public function lastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function next(): State
    {
        $this->lastErrorMessage = null;

        if ($this->endPosition <= $this->position) {
            return State::EndOfDocument;
        }

        $byte = $this->readByteFromData($this->resource, $this->position);
        $this->position++;

        $type = ValueType::from($byte & 7);
        $this->tag = $byte >> 3;

        if ($this->tag == 31) { // the tag is stored in the next byte(s)
            try {
                $varInt = new VarInt($this->resource, $this->position);
                $newTag = $varInt->unserialize();

                if ($newTag > 0xFFFF) {
                    $this->lastErrorMessage = 'Malformed tag-type '.$newTag.' is a too large enum value';

                    return State::Error;
                }

                $this->position = $varInt->getPosition();
                $this->tag = $newTag;
            } catch (\Exception $e) {
                $this->lastErrorMessage = 'Malformed varint; '.$e->getMessage();

                return State::Error;
            }
        }

        switch ($type) {
            case ValueType::NegativeNumber:
            case ValueType::PositiveNumber:
                try {
                    $varInt = new VarInt($this->resource, $this->position);
                    $value = $varInt->unserialize();
                    $this->position = $varInt->getPosition();

                    if ($type == ValueType::NegativeNumber) {
                        $value = $value * -1;
                    }

                    $this->value = $value;
                } catch (\Exception $e) {
                    $this->lastErrorMessage = 'Malformed negative number or positive number; '.$e->getMessage();

                    return State::Error;
                }
                break;

            case ValueType::ByteArray:
            case ValueType::String:
                // ByteArrays and strings will both read into an array. That way we can request in either form later on.
                try {
                    $varInt = new VarInt($this->resource, $this->position);
                    $length = $varInt->unserialize();
                    $this->position = $varInt->getPosition();
                } catch (\Exception $e) {
                    $this->lastErrorMessage = 'Malformed byte array or string; '.$e->getMessage();

                    return State::Error;
                }

                try {
                    $this->value = $this->readBytesFromData($this->resource, $this->position, $length);
                    $this->position += $length;
                } catch (UnserializationException) {
                    return State::EndOfDocument;
                }
                break;

            case ValueType::BoolTrue:
                $this->value = true;
                break;

            case ValueType::BoolFalse:
                $this->value = false;
                break;

            case ValueType::Double:
                try {
                    $bytes = $this->readBytesFromData($this->resource, $this->position, 8);
                    $this->position += 8;
                } catch (UnserializationException) {
                    return State::EndOfDocument;
                }

                // Push them together into a string of bytes then unpack that into the actual value.
                $byteString = '';
                foreach ($bytes as $byte) {
                    $byteString .= chr($byte);
                }

                $unpackedByteStringArray = unpack('e', $byteString);
                // We assert here to help with static analysis.
                assert(is_array($unpackedByteStringArray));
                assert(count($unpackedByteStringArray) == 1);

                $this->value = array_shift($unpackedByteStringArray);
                break;
        }

        return State::FoundTag;
    }

    public function getTag(): int
    {
        return $this->tag;
    }

    public function consumed(): int
    {
        return $this->position;
    }

    /** @param non-negative-int $bytes */
    public function consume(int $bytes): void
    {
        if ($bytes < 0) {
            throw new \InvalidArgumentException('Given $bytes to consume method must be a non-negative integer');
        }

        // If this is an actual stream then we have to consume those bytes: we can't just skip over them.
        if (is_resource($this->resource)) {
            for ($i = 0; $i < $bytes; $i++) {
                $this->readNextByteFromResource($this->resource);
            }
        }

        $this->position += $bytes;
    }

    public function getInt(): int
    {
        if (!is_int($this->value)) {
            throw new UnserializationException('Currently parsed value is not an integer');
        }

        return $this->value;
    }

    public function getBoolean(): bool
    {
        if (!is_bool($this->value)) {
            throw new UnserializationException('Currently parsed value is not a boolean');
        }

        return $this->value;
    }

    public function getString(): string
    {
        if (!is_array($this->value)) {
            throw new UnserializationException('Currently parsed value is not an array');
        }

        $returnValue = '';
        foreach ($this->value as $value) {
            $value = chr($value);
            $returnValue .= $value;
        }

        return $returnValue;
    }

    /** @return list<OutputByte> */
    public function getByteArray(): array
    {
        if (!is_array($this->value)) {
            throw new UnserializationException('Currently parsed value is not an array');
        }

        return $this->value;
    }

    public function getDouble(): float
    {
        if (!is_float($this->value)) {
            throw new UnserializationException('Currently parsed value is not a float');
        }

        return $this->value;
    }
}
