<?php
require __DIR__."/../bootstrap.php";

$clientId     = getenv('CLIENT_ID');
$clientSecret = getenv('CLIENT_SECRET');

if (! isset($argv[1])) {
    exit('Please provide a token to revoke');
}

$jwt = $argv[1];

$result = revokeToken($jwt, $clientId, $clientSecret);
echo $result;
exit();

function revokeToken($jwt, $clientId, $clientSecret)
{
    $payload = http_build_query([
        'token' => $jwt,
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]);

    $url = getenv('OKTA_ISSUER') . '/v1/revoke';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    return curl_exec($ch);
}