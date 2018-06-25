<?php

require __DIR__."/vendor/autoload.php";

define("data", __DIR__."/data");

$query = "call to undefined function";
$st = new Stackoverflow\Stackoverflow($query);
$st = $st->exec();

print_r($st);
