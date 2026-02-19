<?php
function loadEnv($file = '.env') {
    if (!file_exists($file)) {
        die("Arquivo .env não encontrado! Crie um baseado no .env.example");
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        $value = trim($value, '"\'');
        
        if (!defined($name)) {
            define($name, $value);
        }
    }
}

function env($key, $default = null) {
    $value = getenv($key);

    if ($value === false) {
        $path = __DIR__ . '/.env'; 
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;  
                list($name, $val) = explode('=', $line, 2);
                if (trim($name) === $key) {
                    $value = trim($val);
                    break;
                }
            }
        }
    }

    return ($value !== false) ? $value : $default;
}

loadEnv(__DIR__ . '/.env');
?>