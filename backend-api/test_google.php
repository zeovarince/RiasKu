<?php
require __DIR__ . '/vendor/autoload.php';

$clientId = '630970103407-d64mdp7n6mck52alll9vjdjc0ffsn0js.apps.googleusercontent.com';
$client = new Google\Client(['client_id' => $clientId]);

try {
    $res = $client->verifyIdToken('dummy.token');
    echo "Result: " . var_export($res, true) . PHP_EOL;
} catch (Throwable $e) {
    echo "Caught exception: " . $e->getMessage() . PHP_EOL;
}
