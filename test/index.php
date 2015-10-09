<?php

include '../entityframework.php';

header('Content-Type: text/plain');
$ctx = new EntityContext('db.json');

// var_dump($ctx);

echo json_encode(
    $ctx->Person
    ->inject('Location')
    ->single(), JSON_PRETTY_PRINT) . PHP_EOL;
echo json_encode(
    $ctx->StateProvince
    ->inject('Country')
    ->where('CountryCode = "US"')
    ->limit(4)
    ->orderby('Name')
    ->select(), JSON_PRETTY_PRINT) . PHP_EOL;
echo json_encode(
    $ctx->Country
    ->inject('StateProvinces')
    ->where('Code = "CA"')
    ->select(), JSON_PRETTY_PRINT) . PHP_EOL;
// echo json_encode($ctx->schema, JSON_PRETTY_PRINT) . PHP_EOL;

?>