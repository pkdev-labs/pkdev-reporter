<?php

namespace Pkdev\Reporter\Tests\Feature;

use Pkdev\Reporter\Tests\TestCase;

class ConfigDefaultsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['PKDEV_ENABLED'], $_SERVER['PKDEV_ENABLED'], $_ENV['APP_ENV'], $_SERVER['APP_ENV']);

        parent::tearDown();
    }

    private function freshConfig(): array
    {
        return require __DIR__.'/../../config/pkdev-reporter.php';
    }

    public function test_it_is_off_outside_production_and_on_in_production(): void
    {
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
        $this->assertFalse($this->freshConfig()['enabled']);

        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
        $this->assertTrue($this->freshConfig()['enabled']);
    }

    public function test_pkdev_enabled_overrides_the_environment(): void
    {
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
        $_ENV['PKDEV_ENABLED'] = $_SERVER['PKDEV_ENABLED'] = 'true';

        $this->assertTrue($this->freshConfig()['enabled']);
    }

    public function test_the_hub_defaults_to_production(): void
    {
        $this->assertSame('https://app.pkdev.dev', $this->freshConfig()['api_url']);
    }
}
