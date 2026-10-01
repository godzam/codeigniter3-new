<?php

namespace App\Crud;

/**
 * The input types the CRUD generator offers, and what each one is stored as.
 */
final class FieldTypes
{
    public const TEXT = 'text';
    public const TEXTAREA = 'textarea';
    public const NUMBER = 'number';
    public const EMAIL = 'email';
    public const DATE = 'date';
    public const PASSWORD = 'password';
    public const SELECT = 'select';
    public const RADIO = 'radio';
    public const MULTISELECT = 'multiselect';
    public const IMAGE = 'image';
    public const FILE = 'file';

    /** Columns every generated table has besides the declared fields. */
    public const RESERVED_COLUMNS = ['id', 'created_at', 'updated_at'];

    /** Extensions that can run or script in a browser; never accepted as uploads. */
    public const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'pl', 'py', 'rb', 'cgi', 'sh', 'bash', 'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'scr',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'swf', 'jsp', 'asp', 'aspx',
        'htaccess', 'htpasswd', 'ini', 'user',
    ];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @return array<string, string> type => label shown in the builder
     */
    public static function labels()
    {
        return [
            self::TEXT => 'Text',
            self::TEXTAREA => 'Text area',
            self::NUMBER => 'Number',
            self::EMAIL => 'Email',
            self::DATE => 'Date',
            self::PASSWORD => 'Password',
            self::SELECT => 'Dropdown',
            self::RADIO => 'Radio buttons',
            self::MULTISELECT => 'Multi select',
            self::IMAGE => 'Image upload',
            self::FILE => 'File upload',
        ];
    }

    public static function exists($type)
    {
        return isset(self::labels()[$type]);
    }

    public static function hasOptions($type)
    {
        return in_array($type, [self::SELECT, self::RADIO, self::MULTISELECT], true);
    }

    public static function isUpload($type)
    {
        return in_array($type, [self::IMAGE, self::FILE], true);
    }

    /**
     * Types that make sense in the list's search box.
     */
    public static function isSearchable($type)
    {
        return in_array($type, [self::TEXT, self::TEXTAREA, self::NUMBER, self::EMAIL, self::DATE], true);
    }

    /**
     * Unique can only be enforced on a single plain value.
     */
    public static function canBeUnique($type)
    {
        return in_array($type, [self::TEXT, self::NUMBER, self::EMAIL], true);
    }

    /**
     * CodeIgniter dbforge column definition for a field.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    public static function column(array $field)
    {
        switch ($field['type']) {
            case self::TEXTAREA:
            case self::MULTISELECT:
                return ['type' => 'TEXT', 'null' => true];
            case self::NUMBER:
                return !empty($field['integer'])
                    ? ['type' => 'BIGINT', 'null' => true]
                    : ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true];
            case self::DATE:
                return ['type' => 'DATE', 'null' => true];
            case self::EMAIL:
            case self::SELECT:
            case self::RADIO:
                return ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true];
            default: // text, password, image, file
                return ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
    }
}
