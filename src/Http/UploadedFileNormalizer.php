<?php

declare(strict_types=1);

namespace Panulat\Http;

final class UploadedFileNormalizer
{
    /**
     * @param array<string, mixed> $files
     * @return array<string, UploadedFile|array<int|string, mixed>>
     */
    public static function normalize(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (! is_array($file)) {
                continue;
            }

            $normalizedFile = self::normalizeFileSpec($file);

            if ($normalizedFile !== null) {
                $normalized[(string) $key] = $normalizedFile;
            }
        }

        return $normalized;
    }

    public static function first(mixed $value): ?UploadedFile
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $item) {
            $file = self::first($item);

            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }

    /** @return list<UploadedFile> */
    public static function flatten(mixed $value): array
    {
        if ($value instanceof UploadedFile) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $files = [];

        foreach ($value as $item) {
            foreach (self::flatten($item) as $file) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array<int|string, mixed> $file
     * @return UploadedFile|array<int|string, mixed>|null
     */
    private static function normalizeFileSpec(array $file): UploadedFile|array|null
    {
        if (! array_key_exists('name', $file) || ! array_key_exists('tmp_name', $file)) {
            return null;
        }

        if (is_array($file['name'] ?? null)) {
            return self::normalizeNestedFileSpec($file);
        }

        return UploadedFile::fromPhpFile($file);
    }

    /**
     * @param array<int|string, mixed> $file
     * @return array<int|string, mixed>
     */
    private static function normalizeNestedFileSpec(array $file): array
    {
        $normalized = [];
        $names = is_array($file['name'] ?? null) ? $file['name'] : [];
        $types = is_array($file['type'] ?? null) ? $file['type'] : [];
        $tmpNames = is_array($file['tmp_name'] ?? null) ? $file['tmp_name'] : [];
        $errors = is_array($file['error'] ?? null) ? $file['error'] : [];
        $sizes = is_array($file['size'] ?? null) ? $file['size'] : [];

        foreach ($names as $key => $_name) {
            $child = [
                'name' => $names[$key] ?? '',
                'type' => $types[$key] ?? '',
                'tmp_name' => $tmpNames[$key] ?? '',
                'error' => $errors[$key] ?? UPLOAD_ERR_NO_FILE,
                'size' => $sizes[$key] ?? 0,
            ];

            $normalizedFile = self::normalizeFileSpec($child);

            if ($normalizedFile !== null) {
                $normalized[$key] = $normalizedFile;
            }
        }

        return $normalized;
    }
}
