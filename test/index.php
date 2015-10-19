<?php

set_exception_handler(function ($e)
{
    ob_clean();
    echo 'Uncaught exception: ' . $e->getMessage() . PHP_EOL;
    die();
});

include '../entityframework.php';

header('Content-Type: text/plain');
$ctx = new EntityContext('db.json');

echo 'select single Person' . PHP_EOL;
echo json_encode(
    $ctx->Person
    ->inject('Address.StateProvince.Country')
    ->single(),
    JSON_PRETTY_PRINT) . PHP_EOL;
echo 'select single Address' . PHP_EOL;
echo json_encode(
    $ctx->Address
    ->inject('Location')
    ->inject('StateProvince.Country')
    ->where('AddressID = 4')
    ->single(),
    JSON_PRETTY_PRINT) . PHP_EOL;
echo 'select 4 Countries' . PHP_EOL;
echo json_encode(
    $ctx->StateProvince
    ->inject('Country')
    ->where('CountryCode = "US"')
    ->limit(4)
    ->orderby('Name')
    ->select(),
    JSON_PRETTY_PRINT) . PHP_EOL;
echo 'select all Businesses' . PHP_EOL;
echo json_encode(
    $ctx->Business
    ->select(),
    JSON_PRETTY_PRINT) . PHP_EOL;

echo 'call functions' . PHP_EOL;
echo json_encode($ctx->fx_Test(), JSON_PRETTY_PRINT) . PHP_EOL;
echo json_encode($ctx->fx_CountLocationsByRent(9000.0), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'call prodcedures' . PHP_EOL;
// echo json_encode($ctx->p_InsertUpdatePerson(
//     4,
//     null,
//     'Money',
//     null,
//     'Bucks',
//     null,
//     'mr.money.bucks@web.mail',
//     'invalid',
//     'M'
//     ), JSON_PRETTY_PRINT) . PHP_EOL;
// echo json_encode($ctx->p_AllPeople(), JSON_PRETTY_PRINT) . PHP_EOL;
echo json_encode($ctx->p_InsertUpdatePerson(
    4,
    'Mr.',
    'Money',
    null,
    'Bucks',
    null,
    'mr.money.bucks@web.mail',
    '3095549513',
    'M'
    ), JSON_PRETTY_PRINT) . PHP_EOL;
// echo json_encode($ctx->p_AllPeople(), JSON_PRETTY_PRINT) . PHP_EOL;

?>