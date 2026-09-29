<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Refuses files a web server could run or a browser could execute as a page
 * (PHP, HTML, scripts, programs), including tricks like "photo.php.jpg".
 * Design and office files (AI, PSD, PDF, ZIP, SVG...) still go through.
 */
class SafeUpload implements ValidationRule
{
    public const BLOCKED = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
        'html', 'htm', 'xhtml', 'shtml', 'js', 'mjs', 'htaccess', 'htpasswd', 'user.ini', 'ini',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp',
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'jar', 'vbs', 'ps1', 'dll',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $name = strtolower($value->getClientOriginalName());
        $parts = array_slice(explode('.', $name), 1);
        $blocked = array_intersect([...$parts, implode('.', array_slice($parts, -2)), (string) $value->guessExtension()], self::BLOCKED);

        if ($blocked !== [] || str_starts_with($name, '.')) {
            $fail("This file type can't be uploaded, for security. Zip it first if you need to share it.");
        }
    }
}
