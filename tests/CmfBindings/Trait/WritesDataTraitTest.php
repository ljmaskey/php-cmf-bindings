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

namespace CmfBindings\Trait;

use ljmaskey\CmfBindings\Exception\InternalPackageException;
use ljmaskey\CmfBindings\Trait\WritesDataTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WritesDataTrait::class)]
class WritesDataTraitTest extends TestCase
{
    use WritesDataTrait;

    #[Test]
    public function addByteToDataAddsToList(): void
    {
        $inputData = [0x61, 0x66];
        $inputBytes = [0x66, 0x7A];
        $expected = [0x61, 0x66, 0x66, 0x7A];
        foreach ($inputBytes as $inputByte) {
            $this->addByteToData($inputData, $inputByte);
        }

        self::assertEquals($expected, $inputData);
    }

    #[Test]
    public function addByteToDataAddsToResource(): void
    {
        $inputStream = fopen('php://temp', 'w+b');
        self::assertIsResource($inputStream);

        $inputBytes = [0x61, 0x66, 0x66, 0x7A];
        foreach ($inputBytes as $inputByte) {
            $this->addByteToData($inputStream, $inputByte);
        }

        rewind($inputStream);

        // Make sure that we read back exactly each byte.
        foreach ($inputBytes as $expectedByte) {
            // Make sure we are still not finished.
            self::assertFalse(feof($inputStream));

            $actualChar = fread($inputStream, 1);
            self::assertIsString($actualChar);

            $actualByte = ord($actualChar);
            self::assertEquals($expectedByte, $actualByte);
        }

        // At this point we should not know that we are at the end of the file. Read another byte, though, and we should
        // (after receiving an empty string from the read).
        self::assertFalse(feof($inputStream));

        $actualChar = fread($inputStream, 1);
        self::assertEquals('', $actualChar);
        self::assertTrue(feof($inputStream));
    }

    #[Test]
    public function addByteToDataThrowsExceptionWhenGivenANegativeNumber(): void
    {
        self::expectException(InternalPackageException::class);

        $inputData = [];
        $inputByte = -12;

        /* @phpstan-ignore-next-line Explicitly testing that we enforce restrictions on the byte parameter. */
        $this->addByteToData($inputData, $inputByte);
    }

    #[Test]
    public function addByteToDataThrowsExceptionWhenGivenNumberExceedingByte(): void
    {
        self::expectException(InternalPackageException::class);

        $inputData = [];
        $inputByte = 256;

        /* @phpstan-ignore-next-line Explicitly testing that we enforce restrictions on the byte parameter. */
        $this->addByteToData($inputData, $inputByte);
    }
}
