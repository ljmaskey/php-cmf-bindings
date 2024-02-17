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

/**
 * @phpstan-type InputByte int<0, 255>|string
 * @phpstan-type OutputByte int<0, 255>
 * @phpstan-type ByteData resource|list<InputByte>
 */
trait InteractsWithDataTrait
{
    /**
     * Note that our $data parameter is only mixed until we can explicitly specify it as array|resource.
     *
     * @TODO: Once we can explicitly define our parameter as array|resource, change this parameter type.
     *
     * @phpstan-assert ByteData $data
     * @phpstan-assert non-negative-int $position
     */
    private function ensureDataAndPositionParametersAreValid(mixed $data, int $position): void
    {
        $isList = is_array($data) && array_is_list($data);
        $isResource = is_resource($data) && (get_resource_type($data) == 'stream');

        if (!$isList && !$isResource) {
            throw new \InvalidArgumentException('Given $data for '.__CLASS__.' constructor must be either a stream or a list');
        }

        if ($position < 0) {
            throw new \InvalidArgumentException('Given $position for '.__CLASS__.' constructor cannot be negative');
        }

        // This could be simply 'if $isList', but our static analysis does not see that at the moment.
        if (is_array($data) && array_is_list($data)) {
            foreach ($data as $dataElement) {
                $this->ensureIsInputByte($dataElement, \InvalidArgumentException::class);
            }
        }
    }

    /**
     * @phpstan-assert InputByte $inputByte
     *
     * @param class-string<\Exception> $exceptionClass
     */
    private function ensureIsInputByte(mixed $inputByte, string $exceptionClass): void
    {
        $this->inputByteToOutputByte($inputByte, $exceptionClass);
    }

    /**
     * @phpstan-assert InputByte $inputByte
     *
     * @param class-string<\Exception> $exceptionClass
     *
     * @return OutputByte
     */
    private function inputByteToOutputByte(mixed $inputByte, string $exceptionClass): int
    {
        if (is_string($inputByte)) {
            if (strlen($inputByte) != 1) {
                throw new $exceptionClass('Given value for input byte is a non-char string');
            }

            $inputByte = ord($inputByte);
        }

        if (!is_int($inputByte)) {
            throw new $exceptionClass('Given value for input byte is neither a char nor a byte');
        }

        if ($inputByte < 0 || $inputByte > 255) {
            throw new $exceptionClass('Given value for input byte is not a valid byte');
        }

        return $inputByte;
    }
}
