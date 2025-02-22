<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Illuminate\Support\Str;

abstract class Stream
{
    /**
     * Get error from chunk if it exists.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<array<string, mixed>>|null
     */
    protected function chunkError(string $path, array $data): ?array
    {
        $errors = $data['errors'] ?? null;
        if (! is_array($errors)) {
            return null;
        }

        $errorsMatchingPath = array_filter(
            $errors,
            static fn (array $error): bool => Str::startsWith(implode('.', $error['path']), $path)
        );

        return array_values($errorsMatchingPath);
    }
}
