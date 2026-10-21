<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'migrations'");

echo "Found " . count($tables) . " tables to export.\n";

$outputFile = __DIR__ . '/database/printexpress_production_data.sql';
$handle = fopen($outputFile, 'w');

fwrite($handle, "-- Print Express Production Database Dump\n");
fwrite($handle, "-- Generated on " . date('Y-m-d H:i:s') . "\n\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

foreach ($tables as $tableObj) {
    $table = $tableObj->name;
    $rows = DB::table($table)->get();
    if ($rows->isEmpty()) {
        continue;
    }
    
    echo "Exporting {$table} (" . $rows->count() . " rows)...\n";
    fwrite($handle, "-- Data for {$table}\n");
    
    foreach ($rows->chunk(100) as $chunk) {
        foreach ($chunk as $row) {
            $array = (array)$row;
            $columns = array_map(fn($col) => "`$col`", array_keys($array));
            $values = array_map(function($val) {
                if ($val === null) return 'NULL';
                if (is_numeric($val) && !is_string($val)) return $val;
                return "'" . addslashes((string)$val) . "'";
            }, array_values($array));
            
            $sql = "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            fwrite($handle, $sql);
        }
    }
    fwrite($handle, "\n");
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

echo "✅ Database exported successfully to: {$outputFile} (" . filesize($outputFile) . " bytes)\n";
