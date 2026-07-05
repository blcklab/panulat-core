<?php

declare(strict_types=1);

namespace Panulat\Http;

final readonly class UploadedFile
{
    public function __construct(
        private string $tmpName,
        private string $clientFilename,
        private string $clientMimeType,
        private int $size,
        private int $error,
    ) {
    }

    /** @param array<int|string, mixed> $file */
    public static function fromPhpFile(array $file): self
    {
        return new self(
            tmpName: is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '',
            clientFilename: is_string($file['name'] ?? null) ? $file['name'] : '',
            clientMimeType: is_string($file['type'] ?? null) ? $file['type'] : '',
            size: (int) ($file['size'] ?? 0),
            error: (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
        );
    }

    public function tmpName(): string
    {
        return $this->tmpName;
    }

    public function clientFilename(): string
    {
        return $this->clientFilename;
    }

    public function clientMimeType(): string
    {
        return $this->clientMimeType;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function error(): int
    {
        return $this->error;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->tmpName !== ''
            && is_file($this->tmpName);
    }

    public function detectedMimeType(): ?string
    {
        if (! $this->isValid() || ! class_exists(\finfo::class)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($this->tmpName);

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    public function extension(): string
    {
        $extension = pathinfo($this->clientFilename, PATHINFO_EXTENSION);

        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension) ?? '');
    }

    public function safeName(?string $extension = null): string
    {
        $extension = $extension !== null
            ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension) ?? '')
            : $this->extension();

        $name = bin2hex(random_bytes(16));

        return $extension !== '' ? $name . '.' . $extension : $name;
    }

    public function moveTo(string $targetPath): void
    {
        if (! $this->isValid()) {
            throw new \RuntimeException('Cannot move an invalid uploaded file.');
        }

        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            throw new \RuntimeException('Upload target directory does not exist.');
        }

        if (is_uploaded_file($this->tmpName)) {
            if (! move_uploaded_file($this->tmpName, $targetPath)) {
                throw new \RuntimeException('Failed to move uploaded file.');
            }

            return;
        }

        if (! rename($this->tmpName, $targetPath)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }
    }
}
