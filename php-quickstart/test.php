<?php
require __DIR__ . '/../vendor/autoload.php'; // load Composer autoload

$client = new MongoDB\Client("mongodb://localhost:27017");

// chọn database và collection
$db = $client->selectDatabase("testdb");
$collection = $db->selectCollection("users");

// thử insert 1 document
$result = $collection->insertOne([
    "name" => "Kiệt",
    "age"  => 23
]);

echo "Inserted with ID: " . $result->getInsertedId();
?>