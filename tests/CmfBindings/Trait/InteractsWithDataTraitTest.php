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

use ljmaskey\CmfBindings\Trait\InteractsWithDataTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractsWithDataTrait::class)]
class InteractsWithDataTraitTest extends TestCase
{
    use InteractsWithDataTrait;

    #[Test]
    public function ensureDataAndPositionParametersAreValidAcceptsAListOfBytes(): void
    {
        $input = [0x00, 0x00, 0x10, 0x23];
        $this->ensureDataAndPositionParametersAreValid($input, 0);
        self::addToAssertionCount(1);
    }

    #[Test]
    public function ensureDataAndPositionParametersAreValidAcceptsAStream(): void
    {
        $input = fopen('php://temp', 'w+b');
        self::assertIsResource($input);
        $this->ensureDataAndPositionParametersAreValid($input, 0);
        self::addToAssertionCount(1);
    }

    #[Test]
    public function ensureDataAndPositionParametersAreValidThrowsExceptionWhenDataIsNotAResourceOrAList(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $input = 12345;
        $this->ensureDataAndPositionParametersAreValid($input, 0);
    }

    #[Test]
    public function ensureDataAndPositionParametersAreValidThrowsExceptionWhenDataIsANonListArray(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $input = [1 => 0x82, 2 => 0xFE, 3 => 0x7F];
        $this->ensureDataAndPositionParametersAreValid($input, 0);
    }

    #[Test]
    public function ensureDataAndPositionParametersAreValidThrowsExceptionWhenPositionIsNegative(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $input = [0x82, 0xFE, 0x7F];
        $this->ensureDataAndPositionParametersAreValid($input, -4);
    }

    #[Test]
    public function ensureDataAndPositionParametersAreValidThrowsExceptionWhenInvalidArrayBytes(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $input = [0x82, 0xFE, 'bad-byte', 0x7F];
        $this->ensureDataAndPositionParametersAreValid($input, -4);
    }

    #[Test]
    public function inputByteToOutputByteReturnsForInteger(): void
    {
        $anonymousException = new class() extends \Exception { };
        $expected = $input = 0xFE;
        $actual = $this->inputByteToOutputByte($input, $anonymousException::class);

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function inputByteToOutputByteReturnsForChar(): void
    {
        $anonymousException = new class() extends \Exception { };
        $input = 'T';
        $expected = 0x54;
        $actual = $this->inputByteToOutputByte($input, $anonymousException::class);

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function inputByteToOutputByteThrowsExceptionWithNonCharacterString(): void
    {
        $anonymousException = new class() extends \Exception { };
        self::expectException($anonymousException::class);

        $this->inputByteToOutputByte('23', $anonymousException::class);
    }

    #[Test]
    public function inputByteToOutputByteThrowsExceptionWithNonCharOrInt(): void
    {
        $anonymousException = new class() extends \Exception { };
        self::expectException($anonymousException::class);

        $this->inputByteToOutputByte(true, $anonymousException::class);
    }

    #[Test]
    public function inputByteToOutputByteThrowsExceptionWithNegativeByte(): void
    {
        $anonymousException = new class() extends \Exception { };
        self::expectException($anonymousException::class);

        $this->inputByteToOutputByte(-1, $anonymousException::class);
    }

    #[Test]
    public function inputByteToOutputByteThrowsExceptionWithByteExceedingMaximum(): void
    {
        $anonymousException = new class() extends \Exception { };
        self::expectException($anonymousException::class);

        $this->inputByteToOutputByte(256, $anonymousException::class);
    }
}
