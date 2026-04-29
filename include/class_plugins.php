<?php
declare(strict_types=1);

class pluginSystem
{
    /** @var array<string, array<int, array<string, array<string, mixed>>>> */
    public array  $hooks        = [];
    public string $current_hook = '';

    public function add_hook(string $hook, callable|array $function, int $priority = 10, string $file = ''): bool
    {
        if (is_array($function)) {
            // Исправлен баг оригинала: !count($function) == 2 всегда false
            if (count($function) !== 2) {
                return false;
            }

            [$target, $method] = $function;

            if (is_string($target)) {
                $key = $target . '::' . $method;
            } elseif (is_object($target)) {
                $key = spl_object_hash($target) . '->' . $method;
            } else {
                return false;
            }

            if (isset($this->hooks[$hook][$priority][$key])) {
                return true;
            }

            $this->hooks[$hook][$priority][$key] = ['class_method' => $function, 'file' => $file];
        } else {
            $key = (string)$function;

            if (isset($this->hooks[$hook][$priority][$key])) {
                return true;
            }

            $this->hooks[$hook][$priority][$key] = ['function' => $function, 'file' => $file];
        }

        ksort($this->hooks[$hook]);
        return true;
    }

    public function run_hooks(string $hook, mixed &$arguments = ''): mixed
    {
        if (empty($this->hooks[$hook])) {
            return $arguments;
        }

        $this->current_hook = $hook;

        foreach ($this->hooks[$hook] as $hooks) {
            foreach ($hooks as $entry) {
                if (!empty($entry['file'])) {
                    require_once $entry['file'];
                }

                $result = isset($entry['class_method'])
                    ? call_user_func_array($entry['class_method'], [&$arguments])
                    : ($entry['function'])($arguments);

                if ($result !== null) {
                    $arguments = $result;
                }
            }
        }

        $this->current_hook = '';
        return $arguments;
    }
}