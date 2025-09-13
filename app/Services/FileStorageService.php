<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * File Storage Service.
 *
 * Provides an abstraction layer for file storage operations with support for
 * multiple storage drivers: local, MinIO, AWS S3, and custom S3-compatible services.
 *
 * This service encapsulates file operations and provides a unified interface
 * regardless of the underlying storage implementation.
 */
class FileStorageService
{
    private Filesystem $disk;

    private string $diskName;

    public function __construct(?string $diskName = null)
    {
        $defaultDisk = Config::get('filesystems.default', 'minio');
        $this->diskName = $diskName ?? (is_string($defaultDisk) ? $defaultDisk : 'minio');
        $this->disk = Storage::disk($this->diskName);
    }

    /**
     * Store an uploaded file.
     *
     * @param UploadedFile $file The uploaded file to store
     * @param string $path The path where to store the file
     *
     * @throws InvalidArgumentException If file storage fails
     *
     * @return string The stored file path
     */
    public function store(UploadedFile $file, string $path): string
    {
        try {
            Log::info('Storing file', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            $storedPath = $file->storeAs(
                dirname($path),
                basename($path),
                $this->diskName,
            );

            if (!$storedPath) {
                throw new InvalidArgumentException('Failed to store file');
            }

            Log::info('File stored successfully', [
                'path' => $storedPath,
                'disk' => $this->diskName,
            ]);

            return $storedPath;
        } catch (Exception $e) {
            Log::error('File storage failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            throw new InvalidArgumentException('File storage failed: ' . $e->getMessage());
        }
    }

    /**
     * Store file content directly.
     *
     * @param string $path The path where to store the content
     * @param string $content The content to store
     *
     * @return bool True if successful
     */
    public function put(string $path, string $content): bool
    {
        try {
            $result = $this->disk->put($path, $content);

            Log::info('Content stored successfully', [
                'path' => $path,
                'size' => strlen($content),
                'disk' => $this->diskName,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Content storage failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            throw new InvalidArgumentException('Content storage failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if a file exists.
     *
     * @param string $path The file path to check
     *
     * @return bool True if file exists
     */
    public function exists(string $path): bool
    {
        try {
            return $this->disk->exists($path);
        } catch (Exception $e) {
            Log::warning('File existence check failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            return false;
        }
    }

    /**
     * Get file content.
     *
     * @param string $path The file path
     *
     * @throws InvalidArgumentException If file cannot be read
     *
     * @return string File content
     */
    public function get(string $path): string
    {
        try {
            if (!$this->exists($path)) {
                throw new InvalidArgumentException("File does not exist: {$path}");
            }

            $content = $this->disk->get($path);

            if ($content === null) {
                throw new InvalidArgumentException("Failed to read file: {$path}");
            }

            return $content;
        } catch (Exception $e) {
            Log::error('File read failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            throw new InvalidArgumentException('File read failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the full path to a file
     * For local storage returns filesystem path, for S3-like storage returns URL.
     *
     * @param string $path The relative file path
     *
     * @return string The full path or URL
     */
    public function path(string $path): string
    {
        if ($this->diskName === 'local') {
            // For local storage, return filesystem path
            return $this->disk->path($path);
        }

        // For S3-like storage, return the URL
        return $this->url($path);
    }

    /**
     * Get a public URL for a file.
     *
     * @param string $path The file path
     *
     * @throws InvalidArgumentException If URL generation fails
     *
     * @return string The public URL
     */
    public function url(string $path): string
    {
        try {
            if ($this->diskName === 'local') {
                // For local storage, return app URL with storage link
                $appUrl = Config::get('app.url', 'http://localhost');

                return $appUrl . '/storage/' . $path;
            }

            // For S3-like storage, generate temporary URL
            return $this->disk->temporaryUrl($path, now()->addHours(24));
        } catch (Exception $e) {
            Log::error('URL generation failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            throw new InvalidArgumentException('URL generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file.
     *
     * @param string $path The file path to delete
     *
     * @return bool True if successful
     */
    public function delete(string $path): bool
    {
        try {
            if (!$this->exists($path)) {
                Log::info('File does not exist, skipping deletion', [
                    'path' => $path,
                    'disk' => $this->diskName,
                ]);

                return true;
            }

            $result = $this->disk->delete($path);

            Log::info('File deleted successfully', [
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            return false;
        }
    }

    /**
     * Copy a file from one location to another.
     *
     * @param string $from Source path
     * @param string $to Destination path
     *
     * @return bool True if successful
     */
    public function copy(string $from, string $to): bool
    {
        try {
            if (!$this->exists($from)) {
                throw new InvalidArgumentException("Source file does not exist: {$from}");
            }

            $result = $this->disk->copy($from, $to);

            Log::info('File copied successfully', [
                'from' => $from,
                'to' => $to,
                'disk' => $this->diskName,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('File copy failed', [
                'error' => $e->getMessage(),
                'from' => $from,
                'to' => $to,
                'disk' => $this->diskName,
            ]);

            return false;
        }
    }

    /**
     * Get file size in bytes.
     *
     * @param string $path The file path
     *
     * @throws InvalidArgumentException If file size cannot be determined
     *
     * @return int File size in bytes
     */
    public function size(string $path): int
    {
        try {
            if (!$this->exists($path)) {
                throw new InvalidArgumentException("File does not exist: {$path}");
            }

            return $this->disk->size($path);
        } catch (Exception $e) {
            Log::error('File size check failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            throw new InvalidArgumentException('File size check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get file last modified timestamp.
     *
     * @param string $path The file path
     *
     * @throws InvalidArgumentException If timestamp cannot be determined
     *
     * @return int Unix timestamp
     */
    public function lastModified(string $path): int
    {
        try {
            if (!$this->exists($path)) {
                throw new InvalidArgumentException("File does not exist: {$path}");
            }

            return $this->disk->lastModified($path);
        } catch (Exception $e) {
            Log::error('File last modified check failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'disk' => $this->diskName,
            ]);

            throw new InvalidArgumentException('File last modified check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the current disk name.
     *
     * @return string Current disk name
     */
    public function getDiskName(): string
    {
        return $this->diskName;
    }

    /**
     * Get the underlying filesystem instance.
     */
    public function getDisk(): Filesystem
    {
        return $this->disk;
    }

    /**
     * Check if current storage supports public URLs.
     *
     * @return bool True if public URLs are supported
     */
    public function supportsPublicUrls(): bool
    {
        return in_array($this->diskName, ['public', 's3', 'minio', 's3-custom'], true);
    }

    /**
     * Get storage statistics.
     *
     * @return array Storage information
     */
    public function getStorageInfo(): array
    {
        return [
            'disk_name' => $this->diskName,
            'supports_public_urls' => $this->supportsPublicUrls(),
            'driver' => Config::get("filesystems.disks.{$this->diskName}.driver"),
            'bucket' => Config::get("filesystems.disks.{$this->diskName}.bucket"),
            'endpoint' => Config::get("filesystems.disks.{$this->diskName}.endpoint"),
        ];
    }
}
