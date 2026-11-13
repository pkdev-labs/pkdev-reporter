<?php

namespace Pkdev\Reporter\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pkdev\Reporter\Support\Redactor;

class RedactorTest extends TestCase
{
    public static function samples(): array
    {
        return [
            'snake_case env password' => ['MAIL_PASSWORD=hunter2', 'MAIL_PASSWORD=[REDACTED]'],
            'secret inside a longer name' => ['AWS_SECRET_ACCESS_KEY=abc123', 'AWS_SECRET_ACCESS_KEY=[REDACTED]'],
            'site key' => ['PKDEV_SITE_KEY=pk_abc', 'PKDEV_SITE_KEY=[REDACTED]'],
            'app key' => ['APP_KEY=base64:xyz==', 'APP_KEY=[REDACTED]'],
            'plain password' => ['password=hunter2', 'password=[REDACTED]'],
            'json field' => ['{"password":"hunter2","name":"Phil"}', '{"password":"[REDACTED]","name":"Phil"}'],
            'query string' => ['https://api.test/x?api_key=abc&page=2', 'https://api.test/x?api_key=[REDACTED]&page=2'],
            'bearer header' => ['Authorization: Bearer pk_abc123', 'Authorization: [REDACTED] [REDACTED]'],
            'dsn password' => ['mysql://root:hunter2@db', 'mysql://root:[REDACTED]@db'],
            'mysqldump flag' => ['mysqldump -uroot -phunter2 db', 'mysqldump -uroot -p[REDACTED] db'],
            'hyphenated word' => ['Undefined index user-profile', 'Undefined index user-profile'],
            'name only, no value' => ['Undefined array key "password"', 'Undefined array key "password"'],
            'closing parenthesis kept' => [
                "Access denied for user 'forge'@'localhost' (using password: YES)",
                "Access denied for user 'forge'@'localhost' (using password: [REDACTED])",
            ],
            'static call' => ['Call to undefined method App\\Models\\Passport::find()', 'Call to undefined method App\\Models\\Passport::find()'],
        ];
    }

    #[DataProvider('samples')]
    public function test_it_redacts_secrets_and_leaves_ordinary_text(string $input, string $expected): void
    {
        $this->assertSame($expected, Redactor::clean($input));
    }
}
