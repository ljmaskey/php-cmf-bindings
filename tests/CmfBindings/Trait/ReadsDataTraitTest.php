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

namespace ljmaskey\Test\CmfBindings\Trait;

use ljmaskey\CmfBindings\Exception\InternalPackageException;
use ljmaskey\CmfBindings\Exception\UnserializationException;
use ljmaskey\CmfBindings\Trait\ReadsDataTrait;
use ljmaskey\TestTrait\CmfBindings\ListByteStreamTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReadsDataTrait::class)]
class ReadsDataTraitTest extends TestCase
{
    use ReadsDataTrait;
    use ListByteStreamTestTrait;

    #[Test]
    public function readBytesFromDataWorksWithAListOfBytes(): void
    {
        $inputData = [0x65, 0xFC, 0x3E, 0x29, 0xE1, 0x7A];
        $inputPosition = 3;
        $inputNumberOfBytesToRead = 2;
        $expected = [0x29, 0xE1];
        $actual = $this->readBytesFromData($inputData, $inputPosition, $inputNumberOfBytesToRead);

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function readBytesFromDataWorksWithAStreamIgnoringPositionButEnablingContinuedReading(): void
    {
        $inputData = [0x65, 0xFC, 0x3E, 0x29, 0xE1, 0x7A];
        $inputStream = $this->getByteStreamFromList($inputData);

        // Read the first two bytes.
        $inputNumberOfBytesToRead = 2;
        $expected = [0x65, 0xFC];
        $actual = $this->readBytesFromData($inputStream, 999, $inputNumberOfBytesToRead);

        self::assertEquals($expected, $actual);

        // Using the same stream, read the next three bytes.
        $inputNumberOfBytesToRead = 3;
        $expected = [0x3E, 0x29, 0xE1];
        $actual = $this->readBytesFromData($inputStream, 999, $inputNumberOfBytesToRead);

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function readBytesFromDataThrowsExceptionWithNegativeNumberOfBytesToRead(): void
    {
        self::expectException(InternalPackageException::class);

        $input = [];
        /* @phpstan-ignore-next-line Explicitly testing that we enforce restrictions on the number of bytes to read. */
        $this->readBytesFromData($input, 0, -1);
    }

    #[Test]
    public function readByteFromDataWorksWithAListOfBytes(): void
    {
        $inputData = [0x65, 0xFC, 0x3E, 0x29, 0xE1, 0x7A];
        $inputPosition = 3;
        $expected = 0x29;
        $actual = $this->readByteFromData($inputData, $inputPosition);

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function readByteFromDataConvertsInputByteCharToOutputByte(): void
    {
        $inputData = [0x65, 0xFC, 0x3E, 'C', 0x7A];
        $inputPosition = 3;
        $expected = 0x43;
        $actual = $this->readByteFromData($inputData, $inputPosition);

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function readByteFromDataWorksWithAStreamIgnoringPositionButEnablingContinuedReading(): void
    {
        $inputData = [0x65, 0xFC, 0x3E, 0x29, 0xE1, 0x7A];
        $inputStream = $this->getByteStreamFromList($inputData);

        // Read the first byte.
        $expected = 0x65;
        $actual = $this->readByteFromData($inputStream, 999);

        self::assertEquals($expected, $actual);

        // Read the next byte.
        $expected = 0xFC;
        $actual = $this->readByteFromData($inputStream, 999);

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function readByteFromDataThrowsExceptionWhenReadingPastTheEndOfAList(): void
    {
        self::expectException(UnserializationException::class);

        $inputString = random_bytes(16);
        $inputCharacters = str_split($inputString);

        $this->readByteFromData($inputCharacters, 16);
    }

    #[Test]
    public function readByteFromDataThrowsExceptionWhenReadingANonInputByteFromAList(): void
    {
        self::expectException(InternalPackageException::class);

        $inputData = [0x65, 0xFC, 0x3E, 'C', 'bad-byte', 0x7A];
        $inputPosition = 4;

        $this->readByteFromData($inputData, $inputPosition);
    }

    #[Test]
    public function repeatedCallsToReadNextByteFromResourceConsumesResourceAsExpected(): void
    {
        $inputData = [0x65, 0xFC, 0x3E, 0x29, 0xE1, 0x7A];
        $inputStream = $this->getByteStreamFromList($inputData);

        // Check that we read back each character...
        foreach ($inputData as $expectedNextByte) {
            $actualNextByte = $this->readNextByteFromResource($inputStream);
            self::assertEquals($expectedNextByte, $actualNextByte);
        }

        // If we read one more character, we should expect an exception because we are at EOF.
        $caughtException = false;

        try {
            $this->readNextByteFromResource($inputStream);
        } catch (UnserializationException) {
            $caughtException = true;
        }

        self::assertTrue($caughtException);
    }

    #[Test]
    public function readNextByteFromResourceEnforcesResourceParameter(): void
    {
        self::expectException(InternalPackageException::class);

        /* @phpstan-ignore-next-line Explicitly testing that we enforce restrictions on the resource parameter. */
        $this->readNextByteFromResource(12345);
    }

    #[Test]
    public function readNextByteFromResourceThrowsExceptionOnEndOfFile(): void
    {
        self::expectException(UnserializationException::class);

        $stream = fopen('php://temp', 'r');
        self::assertIsResource($stream);
        $this->readNextByteFromResource($stream);
    }
}
