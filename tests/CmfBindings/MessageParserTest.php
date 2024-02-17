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

namespace CmfBindings;

use ljmaskey\CmfBindings\Enum\State;
use ljmaskey\CmfBindings\Exception\UnserializationException;
use ljmaskey\CmfBindings\MessageParser;
use ljmaskey\TestTrait\CmfBindings\ListByteStreamTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageParser::class)]
class MessageParserTest extends TestCase
{
    use ListByteStreamTestTrait;

    #[Test]
    public function throwsExceptionWhenInstantiatedWithNegativeLength(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $input = [];
        /* @phpstan-ignore-next-line Explicitly testing that we enforce restrictions on the length parameter. */
        new MessageParser($input, 0, -1);
    }

    #[Test]
    public function throwsExceptionWhenInstantiatedWithLengthThatWillExceedGivenList(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $input = [0x82, 0xFE, 0x7F];
        new MessageParser($input, 1, 3);
    }

    #[Test]
    public function canReadPositiveInteger(): void
    {
        $input = [0x78, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(15, $sut->getTag());
        self::assertEquals(6512, $sut->getInt());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function canReadNegativeInteger(): void
    {
        $input = [0x79, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(15, $sut->getTag());
        self::assertEquals(-6512, $sut->getInt());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function handlesHigherTags(): void
    {
        $input = [0xF8, 0x80, 0x01, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(129, $sut->getTag());
        self::assertEquals(6512, $sut->getInt());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function canReadString(): void
    {
        $input = [0x0A, 0x04, 0x46, 0xC3, 0xB6, 0x6F];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(1, $sut->getTag());
        self::assertEquals('Föo', $sut->getString());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function canReadByteArrayOrString(): void
    {
        $input = [0xFB, 0x80, 0x48, 0x04, 0x68, 0x69, 0x68, 0x69];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(200, $sut->getTag());
        self::assertEquals([104, 105, 104, 105], $sut->getByteArray());
        self::assertEquals('hihi', $sut->getString());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function canReadBooleanTrue(): void
    {
        $input = [0x1C];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(3, $sut->getTag());
        self::assertTrue($sut->getBoolean());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function canReadBooleanFalse(): void
    {
        $input = [0xFD, 0x28];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(40, $sut->getTag());
        self::assertFalse($sut->getBoolean());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function canReadDouble(): void
    {
        $input = [0x6E, 0x60, 0x3C, 0x83, 0x86, 0x4A, 0x51, 0xC2, 0xC0];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(13, $sut->getTag());
        self::assertEquals(-9378.58223, $sut->getDouble());
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function readingDoubleWhenNotEnoughDataInListReturnsEarly(): void
    {
        $input = [0x6E, 0x60, 0x3C, 0x83, 0x86, 0x4A, 0x51, 0xC2];

        $sut = new MessageParser($input, 0, count($input));
        self::assertEquals(State::EndOfDocument, $sut->next());
    }

    #[Test]
    public function canReadMoreThanOneElement(): void
    {
        $input = [
            0x78, 0xB1, 0x70, // Positive integer: 6512 (tag 15)
            0xF8, 0x80, 0x01, 0xB1, 0x70, // Higher tag: positive integer: 6512 (tag 129)
            0x79, 0xB1, 0x70, // Negative integer: -6512 (tag 15)
            0x0A, 0x04, 0x46, 0xC3, 0xB6, 0x6F, // String: 'Föo' (tag 1)
            0xFB, 0x80, 0x48, 0x04, 0x68, 0x69, 0x68, 0x69, // Byte array / string: 'hihi' (tag 200)
            0x1C, // Boolean: true (tag 3)
            0xFD, 0x28, // Boolean: false (tag 40)
            0x6E, 0x60, 0x3C, 0x83, 0x86, 0x4A, 0x51, 0xC2, 0xC0, // Double: -9378.58223 (tag 13)
        ];

        $sut = new MessageParser($input, 0, count($input));

        // From canAddPositiveInteger
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(15, $sut->getTag());
        self::assertEquals(6512, $sut->getInt());

        // From handlesHigherTags
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(129, $sut->getTag());
        self::assertEquals(6512, $sut->getInt());

        // From canReadNegativeInteger
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(15, $sut->getTag());
        self::assertEquals(-6512, $sut->getInt());

        // From canReadString
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(1, $sut->getTag());
        self::assertEquals('Föo', $sut->getString());

        // From canReadByteArrayOrString
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(200, $sut->getTag());
        self::assertEquals([104, 105, 104, 105], $sut->getByteArray());
        self::assertEquals('hihi', $sut->getString());

        // From canReadBooleanTrue
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(3, $sut->getTag());
        self::assertTrue($sut->getBoolean());

        // From canReadBooleanFalse
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(40, $sut->getTag());
        self::assertFalse($sut->getBoolean());

        // From canReadDouble
        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(13, $sut->getTag());
        self::assertEquals(-9378.58223, $sut->getDouble());

        // And now ensure that there is nothing left.
        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function nextErrorsWhenInputTagExceedsThreshold(): void
    {
        // A boolean true with its tag encoded as 31 (to indicate that the tag is in the next value) is serialized as
        // [0xFC].

        // The maximum tag number is 65535: ensure that it passes first. 65535 is serialized as [0x82, 0xFE, 0x7F].
        $input = [0xFC, 0x82, 0xFE, 0x7F];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(65535, $sut->getTag());
        self::assertTrue($sut->getBoolean());
        self::assertEquals(State::EndOfDocument, $sut->next());

        // 65536 is serialized as [0x82, 0xFF, 0x00].
        $input = [0xFC, 0x82, 0xFF, 0x00];
        $expected = 'Malformed tag-type 65536 is a too large enum value';

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::Error, $sut->next());
        $actual = $sut->lastErrorMessage();
        self::assertIsString($actual);
        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function nextErrorsWhenTagVarIntIsMalformed(): void
    {
        // A boolean true with its tag encoded as 31 (to indicate that the tag is in the next value) is serialized as
        // [0xFC].

        // The maximum tag number is 65535: ensure that it passes. 65535 is serialized as [0x82, 0xFE, 0x7F].
        $input = [0xFC, 0x82, 0xFE];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::Error, $sut->next());
        $actual = $sut->lastErrorMessage();
        self::assertIsString($actual);
        self::assertStringContainsString('Malformed varint', $actual);
    }

    #[Test]
    public function nextErrorsWhenAPositiveNumberIsMalformed(): void
    {
        // Positive integer: 6512 (tag 15) serializes to [0x78, 0xB1, 0x70].
        $input = [0x78, 0xB1];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::Error, $sut->next());
        $actual = $sut->lastErrorMessage();
        self::assertIsString($actual);
        self::assertStringContainsString('Malformed negative number or positive number', $actual);
    }

    #[Test]
    public function nextHitsEndOfDocumentWhenWeRequestAStringLengthThatExceedsListLength(): void
    {
        // 'Föo' string with tag 1 serializes to [0x0A, 0x04, 0x46, 0xC3, 0xB6, 0x6F].
        $input = [0x0A, 0x04, 0x46, 0xC3, 0xB6];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::EndOfDocument, $sut->next());
        // We expect to have only actually read the first two bytes.
        self::assertEquals(2, $sut->consumed());
    }

    #[Test]
    public function nextErrorsWhenTheStringLengthIsMalformed(): void
    {
        $input = [0x0A, 0x82, 0xFF];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::Error, $sut->next());
        $actual = $sut->lastErrorMessage();
        self::assertIsString($actual);
        self::assertStringContainsString('Malformed byte array or string', $actual);
    }

    #[Test]
    public function canConsumeAdditionalBytes(): void
    {
        $input = [
            0x78, 0xB1, 0x70, // Positive integer: 6512 (tag 15)
            0x0A, 0x04, 0x46, 0xC3, 0xB6, 0x6F, // String: 'Föo' (tag 1)
        ];

        $sut = new MessageParser($input, 0, count($input));
        $sut->consume(3);

        self::assertEquals(3, $sut->consumed());

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(1, $sut->getTag());
        self::assertEquals('Föo', $sut->getString());

        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($input), $sut->consumed());
    }

    #[Test]
    public function canConsumeAdditionalBytesFromStream(): void
    {
        $inputBytes = [
            0x78, 0xB1, 0x70, // Positive integer: 6512 (tag 15)
            0x0A, 0x04, 0x46, 0xC3, 0xB6, 0x6F, // String: 'Föo' (tag 1)
        ];
        $input = $this->getByteStreamFromList($inputBytes);

        $sut = new MessageParser($input, 0, count($inputBytes));
        $sut->consume(3);

        self::assertEquals(3, $sut->consumed());

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(1, $sut->getTag());
        self::assertEquals('Föo', $sut->getString());

        self::assertEquals(State::EndOfDocument, $sut->next());
        self::assertEquals(count($inputBytes), $sut->consumed());
    }

    #[Test]
    public function throwsExceptionWhenTryingToConsumeNegativeNumberOfBytes(): void
    {
        self::expectException(\InvalidArgumentException::class);

        $input = [0x78, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));
        /* @phpstan-ignore-next-line Explicitly testing that we enforce restrictions on the bytes parameter. */
        $sut->consume(-1);
    }

    #[Test]
    public function throwsExceptionWhenTryingToGetIntegerAfterReadingBoolean(): void
    {
        self::expectException(UnserializationException::class);

        $input = [0x1C];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertTrue($sut->getBoolean());
        $sut->getInt();
    }

    #[Test]
    public function throwsExceptionWhenTryingToGetBooleanAfterReadingInteger(): void
    {
        self::expectException(UnserializationException::class);

        $input = [0x78, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(6512, $sut->getInt());
        $sut->getBoolean();
    }

    #[Test]
    public function throwsExceptionWhenTryingToGetStringAfterReadingInteger(): void
    {
        self::expectException(UnserializationException::class);

        $input = [0x78, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(6512, $sut->getInt());
        $sut->getString();
    }

    #[Test]
    public function throwsExceptionWhenTryingToGetByteArrayAfterReadingInteger(): void
    {
        self::expectException(UnserializationException::class);

        $input = [0x78, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(6512, $sut->getInt());
        $sut->getByteArray();
    }

    #[Test]
    public function throwsExceptionWhenTryingToGetDoubleAfterReadingInteger(): void
    {
        self::expectException(UnserializationException::class);

        $input = [0x78, 0xB1, 0x70];

        $sut = new MessageParser($input, 0, count($input));

        self::assertEquals(State::FoundTag, $sut->next());
        self::assertEquals(6512, $sut->getInt());
        $sut->getDouble();
    }
}
