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

use ljmaskey\CmfBindings\Exception\InternalPackageException;

/**
 * @phpstan-import-type OutputByte from InteractsWithDataTrait
 * @phpstan-import-type ByteData from InteractsWithDataTrait
 */
trait WritesDataTrait
{
    use InteractsWithDataTrait;

    /**
     * @param ByteData   $data
     * @param OutputByte $byte
     */
    private function addByteToData(mixed &$data, int $byte): void
    {
        if ($byte < 0 || $byte > 255) {
            throw new InternalPackageException('Byte array value '.$byte.' does not represent a single byte');
        }

        if (is_array($data)) {
            $data[] = $byte;
        } else {
            fputs($data, pack('C', $byte), 1);
        }
    }
}
