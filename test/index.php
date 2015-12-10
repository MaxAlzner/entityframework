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
echo 'select 4 States' . PHP_EOL;
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

$person = array(
    'PersonID' => 4,
    'AddressID' => 4,
    'Salutation' => null,
    'FirstName' => 'Person',
    'MiddleName' => 'Junior',
    'LastName' => 'Name',
    'Cadency' => null,
    'EmailAddress' => 'new.account.1@web.mail',
    'PhoneNumber' => '3092143855',
    'GenderCode' => 'M',
    'LastUpdated' => date("Y-m-d H:i:s")
    );
echo 'before attaching Person' . PHP_EOL;
echo json_encode($ctx->Person->where('EmailAddress = "new.account.1@web.mail"')->single(), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'attaching Person' . PHP_EOL;
echo json_encode($ctx
    ->Person
    ->attach($person), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'after attaching Person' . PHP_EOL;
echo json_encode($ctx->Person->where('EmailAddress = "new.account.1@web.mail"')->single(), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'detaching Person' . PHP_EOL;
echo json_encode($ctx
    ->Person
    ->detach($person), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'after detaching Person' . PHP_EOL;
echo json_encode($ctx->Person->where('EmailAddress = "new.account.1@web.mail"')->single(), JSON_PRETTY_PRINT) . PHP_EOL;

echo 'attaching Country' . PHP_EOL;
echo json_encode($ctx
    ->Country
    ->attach(array(
        'Code' => 'UK',
        'Name' => 'United Kingdom'
        )), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'after attaching Country' . PHP_EOL;
echo json_encode($ctx->Country->where('Code = "UK"')->single(), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'detaching Country' . PHP_EOL;
echo json_encode($ctx
    ->Country
    ->detach(array('Name' => 'United Kingdom')), JSON_PRETTY_PRINT) . PHP_EOL;
echo 'after detaching Country' . PHP_EOL;
echo json_encode($ctx->Country->where('Code = "UK"')->single(), JSON_PRETTY_PRINT) . PHP_EOL;

?>