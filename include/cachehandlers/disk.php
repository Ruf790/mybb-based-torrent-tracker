<?php
declare(strict_types=1);

class diskCacheHandler implements CacheHandlerInterface
{
    public function connect(): bool
    {
        return is_writable(TSDIR . '/cache');
    }

    public function fetch(string $name): mixed
    {
        $file = TSDIR . "/cache/{$name}.php";
        if (!file_exists($file)) {
            return false;
        }
        include $file;
        return $$name ?? false;
    }

    public function put(string $name, mixed $contents): bool
    {
        global $mybb;

        $dir = TSDIR . '/cache';
        if (!is_writable($dir)) {
            $mybb->trigger_generic_error('cache_no_write');
            return false;
        }

        $path = "{$dir}/{$name}.php";
        $data = "<?php\n\n/** Generated Cache - Do Not Alter\n"
              . " * Cache Name: {$name}\n"
              . ' * Generated: ' . gmdate('r') . "\n*/\n\n"
              . "\${$name} = " . var_export($contents, true) . ";\n\n?>";

        $fp = fopen($path, 'w');
        if (!$fp) {
            $mybb->trigger_generic_error('cache_no_write');
            return false;
        }

        flock($fp, LOCK_EX);
        fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    public function delete(string $name): bool
    {
        $file = TSDIR . "/cache/{$name}.php";
        return file_exists($file) && unlink($file);
    }

    public function disconnect(): bool
    {
        return true;
    }

    public function size_of(string $name = ''): int
    {
        if ($name !== '') {
            return (int)filesize(TSDIR . "/cache/{$name}.php");
        }

        $total = 0;
        $dir   = TSDIR . '/cache';

        foreach (new DirectoryIterator($dir) as $file) {
            if ($file->isFile()) {
                $total += $file->getSize();
            }
        }

        return $total;
    }
}