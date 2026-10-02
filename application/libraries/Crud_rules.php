<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Crud\ValidationRules;

/**
 * Runs the validation rules chosen for a CRUD field.
 *
 * CodeIgniter's own rules are executed by the real CI_Form_validation methods
 * (alpha_dash, valid_url, min_length, ...) and fail with CodeIgniter's own
 * messages (system/language/<language>/form_validation_lang.php). On top of
 * that: "regex" patterns, matches/differs between fields, and rules
 * registered in application/config/crud_rules.php.
 *
 * Rules only ever see a non-empty value; "required" is handled by the field.
 *
 * @property CI_Lang $lang
 * @property CI_Loader $load
 * @property CI_Form_validation $form_validation
 */
class Crud_rules
{
    protected $CI;

    /** @var array<string, array<string, mixed>>|null */
    protected $registered;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Rules registered in code, with their callables.
     *
     * @return array<string, array<string, mixed>>
     */
    public function registered()
    {
        if ($this->registered === null) {
            $files = array_merge(
                [APPPATH.'config/crud_rules.php'],
                glob(APPPATH.'modules/*/config/crud_rules.php') ?: [],
                [APPPATH.'config/'.ENVIRONMENT.'/crud_rules.php']
            );

            $this->registered = [];
            foreach ($files as $file) {
                foreach (self::definitions($file) as $name => $rule) {
                    if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', (string) $name) || in_array($name, ValidationRules::reservedNames(), true)) {
                        log_message('error', 'crud_rules: "'.$name.'" in '.$file.' is not a usable rule name (lowercase letters, digits, underscores; not a built-in rule name).');

                        continue;
                    }
                    if (!is_array($rule) || !isset($rule['rule']) || !is_callable($rule['rule'])) {
                        log_message('error', 'crud_rules: "'.$name.'" in '.$file.' needs a callable "rule".');

                        continue;
                    }

                    $types = array_values(array_intersect(ValidationRules::TYPES, (array) ($rule['types'] ?? ValidationRules::TYPES)));
                    $this->registered[$name] = [
                        'label' => (string) ($rule['label'] ?? $name),
                        'param' => in_array($rule['param'] ?? 'none', ['none', 'number', 'text'], true) ? $rule['param'] : 'none',
                        'types' => $types === [] ? ValidationRules::TYPES : $types,
                        'message' => (string) ($rule['message'] ?? 'The {field} field is not valid.'),
                        'rule' => $rule['rule'],
                    ];
                }
            }
        }

        return $this->registered;
    }

    /**
     * The registered rules without their callables (for the builder and for
     * validating definitions).
     *
     * @return array<string, array{label: string, param: string, types: array<int, string>}>
     */
    public function meta()
    {
        $meta = [];
        foreach ($this->registered() as $name => $rule) {
            $meta[$name] = ['label' => $rule['label'], 'param' => $rule['param'], 'types' => $rule['types']];
        }

        return $meta;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog()
    {
        return ValidationRules::catalog($this->meta());
    }

    /**
     * Runs the field's rules against a value; returns the first error message, or null.
     *
     * @param array<string, mixed> $module
     * @param array<string, mixed> $field
     * @param array<string, mixed> $input  everything that was posted
     *
     * @return string|null
     */
    public function check(array $module, array $field, $value, array $input)
    {
        $value = (string) $value;
        $builtin = ValidationRules::builtin();
        $labels = array_column($module['fields'], 'label', 'name');

        foreach ($field['rules'] ?? [] as $rule) {
            $name = (string) $rule['rule'];
            $param = (string) $rule['param'];
            $shown = $param;
            $result = true;
            $default = null;

            if ($name === 'regex') {
                // false (a pattern that fails to run) counts as a failure, never as a pass.
                $result = @preg_match($param, $value) === 1;
                $default = $this->line('regex_match', 'The {field} field is not in the correct format.');
            } elseif ($name === 'matches' || $name === 'differs') {
                $other = trim(is_scalar($input[$param] ?? null) ? (string) $input[$param] : '');
                $result = $name === 'matches' ? $value === $other : $value !== $other;
                $shown = (string) ($labels[$param] ?? $param);
                $default = $this->line($name, 'The {field} field is not valid.');
            } elseif (isset($builtin[$name])) {
                $result = $this->builtin($name, $value, $param, $builtin[$name]['param']);
                $default = $this->line($name, 'The {field} field is not valid.');
            } elseif (isset($this->registered()[$name])) {
                $custom = $this->registered()[$name];
                $default = $custom['message'];
                try {
                    $result = ($custom['rule'])($value, $param, $input);
                    if (!is_string($result)) {
                        $result = $result === true; // only a real true passes
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'crud_rules: rule "'.$name.'" threw '.get_class($e).': '.$e->getMessage());
                    $result = false;
                }
            } else {
                // A rule that was removed from the code: do not silently pass, do not lock everyone out either.
                log_message('error', 'crud_rules: module "'.$module['slug'].'" uses an unknown rule "'.$name.'".');

                continue;
            }

            if ($result === true) {
                continue;
            }

            $message = (string) ($rule['message'] ?? '') !== '' ? $rule['message'] : (is_string($result) && $result !== '' ? $result : $default);

            return str_replace(['{field}', '{param}'], [(string) $field['label'], $shown], (string) $message);
        }

        return null;
    }

    /**
     * Calls CodeIgniter's own rule method.
     */
    protected function builtin($name, $value, $param, $paramKind)
    {
        $this->CI->load->library('form_validation');
        $validator = $this->CI->form_validation;

        return (bool) ($paramKind === 'none' ? $validator->{$name}($value) : $validator->{$name}($value, $param));
    }

    /**
     * A CodeIgniter validation message ("The {field} field ...").
     */
    protected function line($rule, $fallback)
    {
        $this->CI->lang->load('form_validation');
        $line = $this->CI->lang->line('form_validation_'.$rule, false);

        return is_string($line) && $line !== '' ? $line : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function definitions($file)
    {
        if (!is_file($file)) {
            return [];
        }

        $config = [];
        include $file;

        return (array) ($config['crud_rules'] ?? []);
    }
}
