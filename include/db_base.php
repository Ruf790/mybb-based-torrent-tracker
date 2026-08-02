<?php

declare(strict_types=1);


interface DB_Base
{
    
    public function connect(array $config): mysqli|false;

  
    public function fetch_array(object $query, int $resulttype = MYSQLI_ASSOC): ?array;

   
    public function fetch_field(object $query, string $field, int|bool $row = false): mixed;

   
    public function data_seek(object $query, int $row): bool;

   
    public function num_rows(object $query): int;

    
    public function insert_id(): int;

   
    public function close(): void;

    
    public function error_number(): int;

    public function error_string(): string;

   
    public function error(string $string = ""): bool;

    
    public function affected_rows(): int;

    
    public function list_tables(string $database): array;

    
    public function table_exists(string $table): bool;

    
    public function field_exists(string $field, string $table): bool;

    
    public function shutdown_query(string|mysqli_result $query, string $name = ''): void;

   

    
    public function escape_string(mixed $string): string;

    
    public function free_result(object $query): bool;

   
    public function escape_string_like(string $string): string;

    
    public function get_version(): string;

  
    public function optimize_table(string $table): object|bool;

   
    public function analyze_table(string $table): object|bool;

    
    public function show_create_table(string $table): string;

   
    public function show_fields_from(string $table): array;

   
    public function is_fulltext(string $table, string $index = ""): bool;

   
    public function supports_fulltext(string $table): bool;

   
    public function supports_fulltext_boolean(string $table): bool;

   
    public function fetch_size(string $table = ''): int;

   
    public function get_execution_time(): ?float;

    
    public function escape_binary(string $string): string;

    
    public function unescape_binary(string $string): string;
}