<?php

namespace Pkdev\Reporter\Support;

use Illuminate\Support\Facades\Log;

/**
 * @internal
 */
class SiteKey
{
    public const FILE = 'app/pkdev-site.key';

    public const FORMAT = '/^pk_[0-9a-f]{36}$/';

    public static function current(): ?string
    {
        return self::configured() ?? self::readFile();
    }

    public static function fromFile(): bool
    {
        return self::configured() === null && self::readFile() !== null;
    }

    public static function ensure(): string
    {
        $current = self::current();

        if ($current !== null) { return $current; }

        $key = 'pk_'.bin2hex(random_bytes(18));
        $path = self::path();
        $temporaryPath = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        file_put_contents($temporaryPath, $key, LOCK_EX);
        self::matchEnvironmentFilePermissions($temporaryPath);
        rename($temporaryPath, $path);

        return $key;
    }

    public static function path(): string
    {
        return storage_path(self::FILE);
    }

    private static function configured(): ?string
    {
        $configured = config('pkdev-reporter.site_key');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    private static function readFile(): ?string
    {
        $path = self::path();

        if (!is_file($path) || !is_readable($path)) { return null; }

        $contents = trim((string) file_get_contents($path));

        return preg_match(self::FORMAT, $contents) ? $contents : null;
    }

    private static function matchEnvironmentFilePermissions(string $path): void
    {
        $environmentFile = app()->environmentFilePath();

        if (!is_file($environmentFile)) {
            chmod($path, 0644);

            return;
        }

        $group = filegroup($environmentFile);

        if ($group !== filegroup($path) && !@chgrp($path, $group)) {
            chmod($path, 0644);
            $groupName = function_exists('posix_getgrgid') ? (posix_getgrgid($group)['name'] ?? (string) $group) : (string) $group;
            Log::warning("[pkdev/reporter] The site key file is world-readable: this user cannot give it group {$groupName} to match .env.");

            return;
        }

        chmod($path, fileperms($environmentFile) & 0777);
    }
}
