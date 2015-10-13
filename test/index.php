<?php

include '../entityframework.php';

header('Content-Type: text/plain');
$ctx = new EntityContext('db.json');

// var_dump($ctx);
// echo ($q0 = $ctx->StateProvince) . PHP_EOL;
// echo ($q1 = $q0->where('CountryCode = "US"')) . PHP_EOL;
// echo ($q2 = $q1->where('Code = "OR"')) . PHP_EOL;
// echo ($q3 = $q2->limit(4)) . PHP_EOL;
// echo ($q4 = $q3->orderby('Name')) . PHP_EOL;
// echo $q1 . PHP_EOL;
// die();

echo json_encode(
    $ctx->Person
    ->inject('Location')
    ->inject('Location.Address')
    ->inject('Location.Address.StateProvince')
    ->inject('Location.Address.StateProvince.Country')
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
    ->select('Code, Name'), JSON_PRETTY_PRINT) . PHP_EOL;
// echo json_encode($ctx->schema, JSON_PRETTY_PRINT) . PHP_EOL;

?>