<?php
if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void {
        if (!is_file($path) || !is_readable($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!$lines) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '') continue;

            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) !== false || array_key_exists($key, $_SERVER) || array_key_exists($key, $_ENV)) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

loadEnvFile(__DIR__ . '/../.env');
loadEnvFile(__DIR__ . '/../.env.local');
if (!getenv('SUPABASE_DB_HOST') && !getenv('SUPABASE_DB_USER') && !getenv('SUPABASE_DB_PASSWORD')) {
    loadEnvFile(__DIR__ . '/../.env.example');
}
