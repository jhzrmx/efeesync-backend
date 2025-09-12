<?php

class EnvLoader {
	/**
     * Load environment variables from a .env file.
     *
     * @param string $filePath The path to the .env file.
     * @return void
     * @throws Exception If the file doesn't exist, cannot be read, or contains mismatched quotes.
     */
    public static function loadFromFile($filePath = ".env") {
        if (!file_exists($filePath)) {
            throw new Exception("The .env file does not exist: " . $filePath);
        }
		
        $lines = explode(PHP_EOL, file_get_contents($filePath));
		
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
			
            $parts = explode('=', $line, 2);
            if (count($parts) < 2) continue;

            $name = trim($parts[0]);
            $value = trim($parts[1]);
			
            if ((substr($value, 0, 1) === '"' && substr($value, -1) !== '"') || 
                (substr($value, 0, 1) === "'" && substr($value, -1) !== "'")) {
                throw new Exception("Mismatched quotes in environment variable: " . $line);
            }
			
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || 
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = self::convertKeyValue($value);
        }
    }

	public static function load($filePath = ".env") {
        return self::loadFromFile($filePath);
    }
    
    private static function convertKeyValue($value) {
        if (strtolower($value) === "true") return true;
		if (strtolower($value) === "false") return false;
		if (filter_var($value, FILTER_VALIDATE_INT) !== false) return intval($value);
		if (filter_var($value, FILTER_VALIDATE_FLOAT) !== false) return floatval($value);
		return $value;
    }
}
