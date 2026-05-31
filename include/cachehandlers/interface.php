<?php
declare(strict_types=1);

interface CacheHandlerInterface
{
    public function connect(): bool;
    public function fetch(string $name): mixed;
    public function put(string $name, mixed $contents): bool;
    public function delete(string $name): bool;
    public function disconnect(): bool;
    public function size_of(string $name = ''): int;
}
