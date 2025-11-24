<?php

declare(strict_types=1);


interface DB_Base
{
    
    public function connect(array $config): mysqli|false;

    public function write_query(string $query, int $hide_errors = 0): mysqli_result|bool;

   
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

   
    public function num_fields(mysqli_result $query): int;

    
    public function list_tables(string $database, string $prefix = ''): array;

    
    public function table_exists(string $table): bool;

    
    public function field_exists(string $field, string $table): bool;

    
    public function shutdown_query(string|mysqli_result $query, string $name = ''): void;

   
    public function simple_select(
        string $table, 
        string $fields = "*", 
        string $conditions = "", 
        array $options = []
    ): mysqli_result|bool;

    
    public function insert_query(string $table, array $array): int|false;

    
    public function insert_query_multiple(string $table, array $array): void;

    
    public function update_query(
        string $table, 
        array $array, 
        string $where = "", 
        string $limit = "", 
        bool $no_quote = false
    ): mysqli_result|bool;

    
    public function delete_query(string $table, string $where = "", string $limit = ""): mysqli_result|bool;

    
    public function escape_string(mixed $string): string;

    
    public function free_result(object $query): bool;

   
    public function escape_string_like(string $string): string;

    
    public function get_version(): string;

  
    public function optimize_table(string $table): mysqli_result|bool;

   
    public function analyze_table(string $table): mysqli_result|bool;

    
    public function show_create_table(string $table): string;

   
    public function show_fields_from(string $table): array;

   
    public function is_fulltext(string $table, string $index = ""): bool;

   
    public function supports_fulltext(string $table): bool;

   
    public function index_exists(string $table, string $index): bool;

   
    public function supports_fulltext_boolean(string $table): bool;

    
    public function create_fulltext_index(string $table, string $column, string $name = ""): mysqli_result|bool;

    
    public function drop_index(string $table, string $name): mysqli_result|bool;

   
    public function drop_table(string $table, bool $hard = false, bool $table_prefix = true): mysqli_result|bool;

   
    public function rename_table(string $old_table, string $new_table, bool $table_prefix = true): mysqli_result|bool;

   
    public function replace_query(
        string $table, 
        array $replacements = [], 
        string|array $default_field = "", 
        bool $insert_id = true
    ): mysqli_result|bool;

    
    public function drop_column(string $table, string $column): mysqli_result|bool;

    
    public function add_column(string $table, string $column, string $definition): mysqli_result|bool;

    
    public function modify_column(
        string $table, 
        string $column, 
        string $new_definition, 
        bool|string $new_not_null = false, 
        bool|string $new_default_value = false
    ): bool;

   
    public function rename_column(
        string $table, 
        string $old_column, 
        string $new_column, 
        string $new_definition, 
        bool|string $new_not_null = false, 
        bool|string $new_default_value = false
    ): bool;

   
    public function set_table_prefix(string $prefix): void;

   
    public function fetch_size(string $table = ''): int;

    
    public function fetch_db_charsets(): array|false;

    
    public function fetch_charset_collation(string $charset): string|false;

    
    public function build_create_table_collation(): string;

   
    public function get_execution_time(): ?float;

    
    public function escape_binary(string $string): string;

    
    public function unescape_binary(string $string): string;
}