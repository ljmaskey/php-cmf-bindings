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

use ljmaskey\CmfBindings\Exception\SerializationException;
use ljmaskey\CmfBindings\Exception\UnserializationException;
use ljmaskey\CmfBindings\VarInt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VarInt::class)]
class VarIntTest extends TestCase
{
    /** @return array{0: int, 1: int<0, 255>[]}[] */
    public static function inputToOutputExamples(): array
    {
        return [
            [127, [0x7F]], // 127: 0x7F
            [128, [0x80, 0x00]], // 128: 0x80
            [255, [0x80, 0x7F]], // 255: 0xFF
            [16_511, [0xFF, 0x7F]], // 16,511: 0x407F
            [16_512, [0x80, 0x80, 0x00]], // 16,512: 0x4080
            [32_767, [0x80, 0xFE, 0x7F]], // 32,767: 0x7FFF
            [65_535, [0x82, 0xFE, 0x7F]], // 65,535: 0xFFFF
            [8_388_607, [0x82, 0xFE, 0xFE, 0x7F]], // 8,388,607: 0x7FFFFF
            [16_777_215, [0x86, 0xFE, 0xFE, 0x7F]], // 16,777,215: 0xFFFFFF
            // Maximum legitimate VarInt. (72,624,976,668,147,839: 0x10204081020407F)
            [72_624_976_668_147_839, [0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0x7F]],
        ];
    }

    /**
     * @param non-negative-int $input
     * @param int<0, 255>[]    $expected
     */
    #[Test]
    #[DataProvider('inputToOutputExamples')]
    public function canSerialize(int $input, array $expected): void
    {
        $output = [];
        $sut = new VarInt($output, 0);

        $actualByteCount = $sut->serialize($input);

        self::assertEquals(count($expected), $actualByteCount);
        self::assertEquals(count($expected), $sut->getPosition());
        self::assertSame($expected, $output);
    }

    #[Test]
    public function serializeThrowsExceptionWhenNumberIsTooLarge(): void
    {
        self::expectException(SerializationException::class);

        $output = [];
        $sut = new VarInt($output, 0);
        $sut->serialize(VarInt::MAXIMUM_ALLOWED_ENCODABLE_NUMBER + 1);
    }

    #[Test]
    public function serializeThrowsExceptionWhenSerializingANegativeNumber(): void
    {
        self::expectException(SerializationException::class);

        $output = [];
        $sut = new VarInt($output, 0);
        /* @phpstan-ignore-next-line Explicitly testing that we enforce restrictions on the value parameter. */
        $sut->serialize(-1);
    }

    /** @param int<0, 255>[] $input */
    #[Test]
    #[DataProvider('inputToOutputExamples')]
    public function canUnserialize(int $expected, array $input): void
    {
        $sut = new VarInt($input, 0);
        $actual = $sut->unserialize();

        self::assertEquals($expected, $actual);
        self::assertEquals(count($input), $sut->getPosition());
    }

    #[Test]
    public function unserializeThrowsExceptionWhenUnableToReadBytes(): void
    {
        self::expectException(UnserializationException::class);

        $output = [];
        $sut = new VarInt($output, 0);
        $sut->unserialize();
    }

    #[Test]
    public function unserializeThrowsExceptionWhenInputDataIsTooLong(): void
    {
        self::expectException(UnserializationException::class);

        // Maximum legitimate VarInt length as per VarInt::MAXIMUM_BYTE_LENGTH
        $input = [0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x80, 0x7F];
        $sut = new VarInt($input, 0);
        $sut->unserialize();
    }
}
