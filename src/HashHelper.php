<?php
/**
 *
 */

namespace DataTables;


class HashHelper
{
    public static function hash($column)
    {
        $hash = hash("sha256", is_array($column) ? serialize($column) : $column);

        return "DT-" . substr($hash, 3);
    }
}