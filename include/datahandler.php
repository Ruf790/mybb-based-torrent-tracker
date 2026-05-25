<?php
declare(strict_types=1);

/**
 * Base data handler class — rewritten for PHP 8.5
 */
class DataHandler
{
    public array  $data           = [];
    public bool   $is_validated   = false;
    public array  $errors         = [];
    public bool   $admin_override = false;
    public $method         = 'insert';
    public $language_prefix = '';
    public $language_file   = '';

    public function __construct(string $method = 'insert')
    {
        if (!in_array($method, ['insert', 'update', 'get', 'delete'], true)) {
            die('A valid method was not supplied to the data handler.');
        }
        $this->method = $method;
    }

    public function set_data(array $data): bool
    {
        $this->data = $data;
        return true;
    }

    public function set_error(string $error, string|array $data = ''): void
    {
        $this->errors[$error] = [
            'error_code' => $error,
            'data'       => $data,
        ];
    }

    public function get_errors(): array
    {
        return $this->errors;
    }

    public function get_friendly_errors(): array
    {
        global $lang;

        if ($this->language_file) {
            $lang->load($this->language_file);
        }

        $errors = [];

        foreach ($this->errors as $error) {
            $lang_key      = $this->language_prefix . '_' . $error['error_code'];
            $error_message = null;

            if (isset($lang->$lang_key)) {
                $error_message = $lang->$lang_key;
            } elseif (isset($lang->{$this->language_file}[$lang_key])) {
                $error_message = $lang->{$this->language_file}[$lang_key];
            } elseif (isset($lang->{$this->language_file}[$error['error_code']])) {
                $error_message = $lang->{$this->language_file}[$error['error_code']];
            }

            if ($error_message === null) {
                $errors[] = $error['error_code'];
                continue;
            }

            $data = !is_array($error['data']) && $error['data'] !== ''
                ? [$error['data']]
                : $error['data'];

            $errors[] = !empty($data)
                ? vsprintf($error_message, $data)
                : $error_message;
        }

        return $errors;
    }

    public function set_validated(bool $validated = true): void
    {
        $this->is_validated = $validated;
    }

    public function get_validated(): bool
    {
        return $this->is_validated;
    }

    public function verify_yesno_option(array &$options, string $option, int $default = 1): void
    {
        if ($this->method !== 'insert' && !array_key_exists($option, $options)) {
            return;
        }

        if (!isset($options[$option]) || $options[$option] === '') {
            $options[$option] = array_key_exists($option, $options) ? 0 : $default;
            return;
        }

        if ($options[$option] != $default) {
            $options[$option] = $default === 1 ? 0 : 1;
        } else {
            $options[$option] = $default;
        }
    }
}