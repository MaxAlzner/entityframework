<?php

include '../entityframework.php';

header('Content-Type: text/plain');
$ctx0 = new EntityContext(array(
    'connection' => array(
        'host' => 'localhost',
        'user' => 'maxalzner',
        'database' => 'c9'
        )));
$ctx1 = new EntityContext('db.json');

// var_dump($ctx0);
// var_dump($ctx1);
var_dump($ctx1->connection);
var_dump($q0 = $ctx1->StateProvince);
var_dump($q1 = $q0->where('Code = "IL"'));
var_dump($q1->select());

echo $q1;

?>