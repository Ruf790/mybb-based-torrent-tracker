<?php

declare(strict_types=1);


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
