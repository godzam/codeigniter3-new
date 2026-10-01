<?php

namespace App\Crud;

/**
 * Stores one uploaded file for a CRUD field: checks size, extension and
 * content, gives it a random name, and (for images) compresses it.
 * Files go to <uploads>/crud/<slug>/ and are referenced by the relative path
 * "crud/<slug>/<random>.<ext>".
 */
final class Uploader
{
    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif',
    ];

    /** @var string */
    private $root;

    /** @var bool whether to require PHP's is_uploaded_file() (off only in tests) */
    private $requireHttpUpload;

    /**
     * @param string $root the public uploads directory, e.g. FCPATH.'uploads'
     */
    public function __construct($root, $requireHttpUpload = true)
    {
        $this->root = rtrim($root, '/\\');
        $this->requireHttpUpload = $requireHttpUpload;
    }

    /**
     * @param array<string, mixed> $file  one entry of $_FILES
     * @param array<string, mixed> $field the field definition
     *
     * @return array{path: string|null, error: string|null}
     */
    public function store(array $file, array $field, $slug)
    {
        $label = $field['label'];
        $upload = $field['upload'];

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return self::fail(self::phpError($error, $label));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || ($this->requireHttpUpload && !is_uploaded_file($tmp))) {
            return self::fail($label.' was not uploaded correctly.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return self::fail($label.' is empty.');
        }
        if ($size > $upload['max_kb'] * 1024) {
            return self::fail($label.' is larger than '.self::size($upload['max_kb']).'.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, FieldTypes::DANGEROUS_EXTENSIONS, true) || !in_array($extension, $upload['types'], true)) {
            return self::fail($label.' must be one of: '.implode(', ', $upload['types']).'.');
        }

        $isImage = $field['type'] === FieldTypes::IMAGE;
        $mime = null;

        if ($isImage) {
            $mime = self::detectMime($tmp);
            if (!isset(self::MIME_BY_EXTENSION[$extension]) || $mime !== self::MIME_BY_EXTENSION[$extension] || @getimagesize($tmp) === false) {
                return self::fail($label.' is not a valid '.strtoupper($extension).' image.');
            }
        } elseif (self::looksLikeScript($tmp)) {
            return self::fail($label.' contains code and was rejected.');
        }

        $dir = $this->root.'/crud/'.$slug;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return self::fail('The upload folder is not writable.');
        }

        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $dest = $dir.'/'.$name;

        $done = false;
        if ($isImage && $mime !== null) {
            $done = ImageCompressor::compress($tmp, $dest, $mime, (int) $upload['max_width'], (int) $upload['quality']);
            // Compression can make a small image bigger; keep the smaller file.
            if ($done && is_file($dest) && filesize($dest) > $size && $mime !== 'image/gif') {
                $done = false;
            }
        }
        if (!$done) {
            $done = $this->requireHttpUpload ? @move_uploaded_file($tmp, $dest) : @copy($tmp, $dest);
        }
        if (!$done) {
            return self::fail('The file could not be saved.');
        }

        @chmod($dest, 0644);

        return ['path' => 'crud/'.$slug.'/'.$name, 'error' => null];
    }

    /**
     * Deletes a stored file. Only paths this class could have produced are
     * accepted, so a tampered value can never reach another file.
     */
    public function delete($relative)
    {
        if (!self::validPath((string) $relative)) {
            return false;
        }

        $file = $this->root.'/'.$relative;

        return is_file($file) && @unlink($file);
    }

    /**
     * Deletes every file of a module (when the module is removed).
     */
    public function deleteAll($slug)
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', (string) $slug)) {
            return;
        }

        foreach (glob($this->root.'/crud/'.$slug.'/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->root.'/crud/'.$slug);
    }

    public static function validPath($relative)
    {
        return (bool) preg_match('#^crud/[a-z][a-z0-9_]*/[a-f0-9]{32}\.[a-z0-9]{1,10}$#', $relative);
    }

    private static function fail($message)
    {
        return ['path' => null, 'error' => $message];
    }

    private static function detectMime($file)
    {
        if (!function_exists('finfo_open')) {
            $info = @getimagesize($file);

            return $info ? $info['mime'] : null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        return $mime ?: null;
    }

    /**
     * A cheap check for server-side script hidden in a "document".
     */
    private static function looksLikeScript($file)
    {
        $head = (string) @file_get_contents($file, false, null, 0, 8192);

        return (bool) preg_match('/<\?(php|=)|<%@|#!\s*\/(usr\/)?bin\//i', $head);
    }

    private static function size($kb)
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : $kb.' KB';
    }

    private static function phpError($code, $label)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $label.' is larger than the server allows (upload_max_filesize is '.ini_get('upload_max_filesize').').';
            case UPLOAD_ERR_PARTIAL:
                return $label.' was only partly uploaded. Try again.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                return 'The server could not store the upload.';
            default:
                return $label.' could not be uploaded.';
        }
    }
}
