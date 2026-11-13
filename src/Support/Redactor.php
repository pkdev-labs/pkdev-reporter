<?php

namespace Pkdev\Reporter\Support;

class Redactor
{
    public static function clean(string $text): string
    {
        $text = preg_replace('/\b(bearer)\s+\S+/i', '$1 [REDACTED]', $text);

        // Key=value patterns (env vars, JSON fields, query strings), matched inside snake_case names
        $text = preg_replace(
            '/\b(\w*(?:pass|pwd|secret|token|credential|authorization|(?:api|access|private|site|app|client|encryption)_?key)\w*)'
            .'([\'"]?\s*(?:=|:(?!:))\s*[\'"]?)[^\s\'",&)]+/i',
            '$1$2[REDACTED]',
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
            '/(?<![\w-])(-p)(\S+)/',
            '$1[REDACTED]',
            $text,
        );

        return $text;
    }
}
