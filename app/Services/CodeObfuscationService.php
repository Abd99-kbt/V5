<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CodeObfuscationService
{
    protected $obfuscationKey;
    protected $excludedFiles = [
        'config/',
        'database/migrations/',
        'resources/views/',
        'routes/',
        'storage/',
        'vendor/',
        'node_modules/',
        'public/',
        '.git/',
        'tests/',
    ];

    protected $excludedExtensions = [
        '.md',
        '.txt',
        '.json',
        '.xml',
        '.yml',
        '.yaml',
        '.gitignore',
        '.gitattributes',
        '.env',
    ];

    public function __construct()
    {
        $this->obfuscationKey = config('license.encryption_key', env('APP_KEY'));
    }

    /**
     * Obfuscate PHP files in the project
     */
    public function obfuscateCode($directory = null, $options = [])
    {
        $directory = $directory ?: base_path();
        $options = array_merge([
            'obfuscate_variables' => config('license.obfuscation.obfuscate_variables', true),
            'obfuscate_functions' => config('license.obfuscation.obfuscate_functions', true),
            'obfuscate_classes' => config('license.obfuscation.obfuscate_classes', true),
            'compress' => true,
            'backup' => true,
        ], $options);

        if ($options['backup']) {
            $this->createBackup();
        }

        $files = $this->getPhpFiles($directory);

        Log::info('Starting code obfuscation', ['files_count' => count($files)]);

        foreach ($files as $file) {
            $this->obfuscateFile($file, $options);
        }

        Log::info('Code obfuscation completed');
        return ['success' => true, 'files_processed' => count($files)];
    }

    /**
     * Get all PHP files that can be obfuscated
     */
    protected function getPhpFiles($directory)
    {
        $files = [];

        $items = File::allFiles($directory);

        foreach ($items as $item) {
            $path = $item->getPathname();

            // Skip excluded directories
            if ($this->isExcludedPath($path)) {
                continue;
            }

            // Only process PHP files
            if ($item->getExtension() === 'php') {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Check if path should be excluded from obfuscation
     */
    protected function isExcludedPath($path)
    {
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

        // Check excluded directories
        foreach ($this->excludedFiles as $excluded) {
            if (Str::startsWith($relativePath, $excluded)) {
                return true;
            }
        }

        // Check excluded extensions
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array('.' . $extension, $this->excludedExtensions)) {
            return true;
        }

        return false;
    }

    /**
     * Obfuscate a single PHP file
     */
    protected function obfuscateFile($filePath, $options)
    {
        $content = File::get($filePath);

        // Skip if file is already obfuscated (has obfuscation marker)
        if (Str::contains($content, '/* OBFUSCATED CODE */')) {
            return;
        }

        $originalContent = $content;

        // Obfuscate variables
        if ($options['obfuscate_variables']) {
            $content = $this->obfuscateVariables($content);
        }

        // Obfuscate function names
        if ($options['obfuscate_functions']) {
            $content = $this->obfuscateFunctions($content);
        }

        // Obfuscate class names
        if ($options['obfuscate_classes']) {
            $content = $this->obfuscateClasses($content);
        }

        // Compress code (remove extra whitespace)
        if ($options['compress']) {
            $content = $this->compressCode($content);
        }

        // Add obfuscation marker
        $content = "/* OBFUSCATED CODE */\n" . $content;

        // Encrypt sensitive parts
        $content = $this->encryptSensitiveParts($content);

        File::put($filePath, $content);

        Log::debug('File obfuscated', ['file' => $filePath]);
    }

    /**
     * Obfuscate variable names
     */
    protected function obfuscateVariables($content)
    {
        // Simple variable name obfuscation
        // This is a basic implementation - in production, you'd want more sophisticated obfuscation
        $patterns = [
            '/\$([a-zA-Z_][a-zA-Z0-9_]*)/' => function ($matches) {
                $varName = $matches[1];
                // Skip common variables and superglobals
                if (in_array($varName, ['this', '_GET', '_POST', '_SESSION', '_SERVER', '_FILES', '_REQUEST'])) {
                    return $matches[0];
                }
                return '$' . $this->generateObfuscatedName($varName);
            }
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace_callback($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Obfuscate function names
     */
    protected function obfuscateFunctions($content)
    {
        // Skip built-in PHP functions
        $builtinFunctions = [
            'echo', 'print', 'isset', 'empty', 'unset', 'die', 'exit',
            'include', 'require', 'include_once', 'require_once',
            'count', 'strlen', 'strpos', 'substr', 'trim', 'explode', 'implode'
        ];

        $content = preg_replace_callback(
            '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',
            function ($matches) use ($builtinFunctions) {
                $funcName = $matches[1];
                if (in_array($funcName, $builtinFunctions)) {
                    return $matches[0];
                }
                return 'function ' . $this->generateObfuscatedName($funcName) . '(';
            },
            $content
        );

        return $content;
    }

    /**
     * Obfuscate class names
     */
    protected function obfuscateClasses($content)
    {
        $content = preg_replace_callback(
            '/class\s+([a-zA-Z_][a-zA-Z0-9_]*)/',
            function ($matches) {
                return 'class ' . $this->generateObfuscatedName($matches[1]);
            },
            $content
        );

        return $content;
    }

    /**
     * Compress code by removing extra whitespace
     */
    protected function compressCode($content)
    {
        // Remove comments
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        $content = preg_replace('/\/\/.*$/m', '', $content);

        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\s*([{}();,=+\-*\/])\s*/', '$1', $content);

        return $content;
    }

    /**
     * Encrypt sensitive parts of the code
     */
    protected function encryptSensitiveParts($content)
    {
        // Encrypt API keys, database credentials, etc.
        $patterns = [
            '/(["\'])(APP_KEY|DB_PASSWORD|API_KEY|SECRET_KEY)\1\s*:\s*(["\'])([^"\']+)\3/',
            '/define\s*\(\s*(["\'])(APP_KEY|DB_PASSWORD|API_KEY|SECRET_KEY)\1\s*,\s*(["\'])([^"\']+)\3\s*\)/'
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback($pattern, function ($matches) {
                $encrypted = $this->encryptString($matches[4]);
                return str_replace($matches[4], 'base64_decode("' . base64_encode($encrypted) . '")', $matches[0]);
            }, $content);
        }

        return $content;
    }

    /**
     * Generate an obfuscated name
     */
    protected function generateObfuscatedName($original)
    {
        // Create a hash-based obfuscated name
        $hash = substr(md5($original . $this->obfuscationKey), 0, 8);
        return '_' . $hash;
    }

    /**
     * Encrypt a string
     */
    protected function encryptString($string)
    {
        return openssl_encrypt($string, 'AES-256-CBC', $this->obfuscationKey, 0, substr($this->obfuscationKey, 0, 16));
    }

    /**
     * Create backup before obfuscation
     */
    protected function createBackup()
    {
        $backupDir = storage_path('backups/code_' . date('Y-m-d_H-i-s'));

        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        // Copy app directory
        $this->copyDirectory(app_path(), $backupDir . '/app');

        Log::info('Backup created', ['backup_path' => $backupDir]);
    }

    /**
     * Copy directory recursively
     */
    protected function copyDirectory($src, $dst)
    {
        if (!File::exists($dst)) {
            File::makeDirectory($dst, 0755, true);
        }

        $files = File::allFiles($src);

        foreach ($files as $file) {
            $relativePath = str_replace($src . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $destPath = $dst . DIRECTORY_SEPARATOR . $relativePath;

            $destDir = dirname($destPath);
            if (!File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            File::copy($file->getPathname(), $destPath);
        }
    }

    /**
     * Restore from backup
     */
    public function restoreFromBackup($backupPath)
    {
        if (!File::exists($backupPath)) {
            return ['success' => false, 'message' => 'Backup not found'];
        }

        // Restore app directory
        $appBackup = $backupPath . '/app';
        if (File::exists($appBackup)) {
            $this->copyDirectory($appBackup, app_path());
        }

        Log::info('Restored from backup', ['backup_path' => $backupPath]);
        return ['success' => true];
    }

    /**
     * Check if code is obfuscated
     */
    public function isObfuscated($filePath)
    {
        if (!File::exists($filePath)) {
            return false;
        }

        $content = File::get($filePath);
        return Str::contains($content, '/* OBFUSCATED CODE */');
    }
}