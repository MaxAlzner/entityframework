<?php

include '../entityframework.php';

header('Content-Type: text/plain');
$ctx = new EntityContext('db.json');

echo json_encode(
    $ctx->Person
    ->inject('Location')
    ->inject('Location.Address')
    ->inject('Location.Address.StateProvince')
    ->inject('Location.Address.StateProvince.Country')
    ->single(), JSON_PRETTY_PRINT) . PHP_EOL;
echo json_encode(
    $ctx->Address
    ->inject('Locations')
    ->inject('StateProvince')
    ->inject('StateProvince.Country')
    ->where('AddressID = 4')
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

?>