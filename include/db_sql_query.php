<?php

declare(strict_types=1);

/**
 * db_admin_raw.php
 *
 * Раньше это была общая функция sql_query() прямо в классе DB_MySQLi -
 * доступная (и потому соблазнительная для случайного использования) из
 * абсолютно любого файла проекта, включая обычный юзер-фейсинг код, где
 * значения ДОЛЖНЫ идти через sql_query_prepared() с `?`-плейсхолдерами.
 *
 * Вынесена сюда намеренно, отдельным файлом, который нужно явно
 * require_once там, где он реально нужен. Это узкий, специально
 * изолированный инструмент для конкретного класса случаев, где
 * sql_query_prepared() физически не работает:
 *
 *   1. Административные команды, которые MySQL не поддерживает через
 *      protocol prepared statements вообще (CHECK TABLE, REPAIR TABLE,
 *      OPTIMIZE TABLE, ANALYZE TABLE, KILL <id>, SHOW VARIABLES, и т.п.) -
 *      попытка выполнить их через sql_query_prepared() падает с ошибкой
 *      MySQL "This command is not supported in the prepared statement
 *      protocol yet", это ограничение сервера, а не нашей обёртки.
 *
 *   2. Случаи, где динамическая часть запроса — это ИДЕНТИФИКАТОР
 *      (имя таблицы/колонки/переменной), а не значение. `?`-плейсхолдеры
 *      параметризуют только значения (VALUES/WHERE), никогда не имена -
 *      для идентификаторов единственный безопасный путь - строгая
 *      whitelist/regex-валидация формата + подстановка в обратных
 *      кавычках, что и должен делать вызывающий код ДО передачи сюда.
 *
 * НЕ используйте этот файл для обычных запросов со значениями от
 * пользователя (WHERE id = $id, INSERT ... VALUES ($x) и т.п.) - там
 * всегда должен быть $db->sql_query_prepared() с `?`.
 */

/**
 * Выполняет "сырой" административный SQL-запрос напрямую через mysqli,
 * в обход $db->sql_query_prepared() (которая для таких команд не работает)
 * и без больше не существующего $db->sql_query().
 *
 * @param object $db      Экземпляр DB_MySQLi ($db->read_link/write_link публичные)
 * @param string $sql     Готовая SQL-команда. Вызывающий код отвечает за то,
 *                         что любые динамические идентификаторы в ней уже
 *                         провалидированы и заключены в обратные кавычки -
 *                         эта функция НЕ экранирует и НЕ валидирует ничего.
 * @param bool   $write   true - выполнить через write_link (например, REPAIR
 *                         TABLE реально пишет), false - через read_link.
 */
function db_admin_raw_query(object $db, string $sql, bool $write = false): mysqli_result|bool
{
    $link = ($write && $db->write_link) ? $db->write_link : $db->read_link;

    try {
        $result = mysqli_query($link, $sql);
    } catch (mysqli_sql_exception $e) {
        if (method_exists($db, 'output_error')) {
            $db->output_error(defined('MYBB_SQL') ? MYBB_SQL : 1, [
                'error_no' => $e->getCode(),
                'error'    => $e->getMessage(),
                'query'    => $sql,
            ], __FILE__, __LINE__);
        }
        return false;
    }

    return $result;
}
