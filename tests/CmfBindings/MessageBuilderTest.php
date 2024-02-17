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

namespace ljmaskey\Test\CmfBindings;

use ljmaskey\CmfBindings\MessageBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageBuilder::class)]
class MessageBuilderTest extends TestCase
{
    #[Test]
    public function canAddPositiveInteger(): void
    {
        $actual = [];
        $expected = [0x78, 0xB1, 0x70];

        $sut = new MessageBuilder($actual, 0);
        $sut->addInt(15, 6512);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function canAddNegativeInteger(): void
    {
        $actual = [];
        $expected = [0x79, 0xB1, 0x70];

        $sut = new MessageBuilder($actual, 0);
        $sut->addInt(15, -6512);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function writeHandlesHigherTags(): void
    {
        $actual = [];
        $expected = [0xF8, 0x80, 0x01, 0xB1, 0x70];

        $sut = new MessageBuilder($actual, 0);
        $sut->addInt(129, 6512);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function writeThrowsExceptionWhenGivenANegativeTag(): void
    {
        self::expectException(\InvalidArgumentException::class);
        $input = [];

        $sut = new MessageBuilder($input, 0);
        $sut->addInt(-1, 6512);
    }

    #[Test]
    public function writeThrowsExceptionWhenGivenATagThatExceedsTheMaximum(): void
    {
        self::expectException(\InvalidArgumentException::class);
        $input = [];

        $sut = new MessageBuilder($input, 0);
        $sut->addInt(65536, 6512);
    }

    #[Test]
    public function canAddString(): void
    {
        $actual = [];
        $expected = [0x0A, 0x04, 0x46, 0xC3, 0xB6, 0x6F];

        $sut = new MessageBuilder($actual, 0);
        $sut->addString(1, 'Föo');

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function canAddByteArray(): void
    {
        $actual = [];
        $expected = [0xFB, 0x80, 0x48, 0x04, 0x68, 0x69, 0x68, 0x69];

        $sut = new MessageBuilder($actual, 0);
        $sut->addByteArray(200, ['h', 'i', ord('h'), ord('i')]);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function canAddBooleanTrue(): void
    {
        $actual = [];
        $expected = [0x1C];

        $sut = new MessageBuilder($actual, 0);
        $sut->addBoolean(3, true);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function canAddBooleanFalse(): void
    {
        $actual = [];
        $expected = [0xFD, 0x28];

        $sut = new MessageBuilder($actual, 0);
        $sut->addBoolean(40, false);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function canAddDouble(): void
    {
        $actual = [];
        $expected = [0x6E, 0x60, 0x3C, 0x83, 0x86, 0x4A, 0x51, 0xC2, 0xC0];

        $sut = new MessageBuilder($actual, 0);
        $sut->addDouble(13, -9378.58223);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }

    #[Test]
    public function canAddMoreThanOneElement(): void
    {
        $actual = [];
        $expected = [];
        $sut = new MessageBuilder($actual, 0);

        // From canAddPositiveInteger
        $sut->addInt(15, 6512);
        $expected = array_merge($expected, [0x78, 0xB1, 0x70]);

        // From canAddNegativeInteger
        $sut->addInt(15, -6512);
        $expected = array_merge($expected, [0x79, 0xB1, 0x70]);

        // From canAddString
        $sut->addString(1, 'Föo');
        $expected = array_merge($expected, [0x0A, 0x04, 0x46, 0xC3, 0xB6, 0x6F]);

        // From canAddByteArray
        $sut->addByteArray(200, ['h', 'i', 'h', 'i']);
        $expected = array_merge($expected, [0xFB, 0x80, 0x48, 0x04,  0x68,  0x69,  0x68, 0x69]);

        // From canAddBooleanTrue
        $sut->addBoolean(3, true);
        $expected = array_merge($expected, [0x1C]);

        // From canAddBooleanFalse
        $sut->addBoolean(40, false);
        $expected = array_merge($expected, [0xFD, 0x28]);

        // From canAddDouble
        $sut->addDouble(13, -9378.58223);
        $expected = array_merge($expected, [0x6E, 0x60, 0x3C, 0x83, 0x86, 0x4A, 0x51, 0xC2, 0xC0]);

        self::assertEquals($expected, $actual);
        self::assertEquals(count($expected), $sut->getPosition());
    }
}
