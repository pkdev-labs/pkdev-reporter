<?php

namespace Pkdev\Reporter\Support;

class Redactor
{
    public static function clean(string $text): string
    {
        // Key=value patterns (env vars, JSON fields, query strings)
        $text = preg_replace(
            '/\b(password|passwd|pwd|pass|secret|token|api_key|apikey|access_token|refresh_token|'
            . 'client_secret|authorization|auth|bearer|credential|private_key|database_url|db_password)'
            . '[=:\s\'"]+\S+/i',
            '$1=[REDACTED]',
            $text,
        );

        // DSN-embedded passwords: scheme://user:PASSWORD@host
        $text = preg_replace(
            '#(\w+://[^:@\s]+):([^@\s]+)@#',
            '$1:[REDACTED]@',
            $text,
        );

        // Shell-style positional password args: -pSECRET or --password=SECRET
        $text = preg_replace(
            '/(-p)(\S+)/',
            '$1[REDACTED]',
            $text,
        );

        return $text;
    }
}
