<?php
// includes/env_loader.php - Lightweight .env loader for local & server environments
if (!function_exists('load_app_env')) {
    function load_app_env($path = null) {
        if ($path === null) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        }
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos !== false) {
                $key = trim(substr($line, 0, $pos));
                $val = trim(substr($line, $pos + 1));

                $len = strlen($val);
                if ($len >= 2) {
                    $first = $val[0];
                    $last  = $val[$len - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $val = substr($val, 1, -1);
                    }
                }

                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

load_app_env();
