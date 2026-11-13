<?php

namespace Pkdev\Reporter\Tests\Feature;

use Pkdev\Reporter\Support\SiteKey;
use Pkdev\Reporter\Tests\TestCase;

class SiteKeyTest extends TestCase
{
    public function test_a_configured_key_wins_over_the_file(): void
    {
        file_put_contents(SiteKey::path(), 'pk_'.str_repeat('a', 36));
        config(['pkdev-reporter.site_key' => 'pk_from_env']);

        $this->assertSame('pk_from_env', SiteKey::current());
        $this->assertFalse(SiteKey::fromFile());
        $this->assertSame('pk_from_env', SiteKey::ensure());
    }

    public function test_the_file_key_is_used_when_none_is_configured(): void
    {
        file_put_contents(SiteKey::path(), 'pk_'.str_repeat('b', 36)."\n");

        $this->assertSame('pk_'.str_repeat('b', 36), SiteKey::current());
        $this->assertTrue(SiteKey::fromFile());
    }

    public function test_a_malformed_file_is_ignored(): void
    {
        file_put_contents(SiteKey::path(), 'not a key');

        $this->assertNull(SiteKey::current());
    }

    public function test_ensure_writes_a_well_formed_key_with_the_env_files_mode(): void
    {
        file_put_contents($this->sandbox.'/.env', "APP_NAME=Test\n");
        chmod($this->sandbox.'/.env', 0640);

        $key = SiteKey::ensure();

        $this->assertMatchesRegularExpression(SiteKey::FORMAT, $key);
        $this->assertSame($key, file_get_contents(SiteKey::path()));
        $this->assertSame(0640, fileperms(SiteKey::path()) & 0777);
        $this->assertSame(filegroup($this->sandbox.'/.env'), filegroup(SiteKey::path()));
        $this->assertSame([], glob(SiteKey::path().'.*.tmp'));
    }

    public function test_without_an_env_file_the_key_is_readable_by_the_web_user(): void
    {
        SiteKey::ensure();

        $this->assertSame(0644, fileperms(SiteKey::path()) & 0777);
    }

    public function test_ensure_never_replaces_an_existing_key(): void
    {
        $first = SiteKey::ensure();

        $this->assertSame($first, SiteKey::ensure());
    }
}
