<?php
require __DIR__."/../bootstrap.php";

$clientId         = getenv('CLIENT_ID');
$clientSecret     = getenv('CLIENT_SECRET');
$scope            = getenv('SCOPE');

// obtain an access token
$token = obtainToken($issuer, $clientId, $clientSecret, $scope);

echo $token;
echo "\n";


function obtainToken($issuer, $clientId, $clientSecret, $scope)
{
    $metadataUrl = getenv('OPENID_CONFIGURATION');
    $metadata = http($metadataUrl);
    $tokenUrl = $metadata['token_endpoint'];
    // prepare the request
    
    $token = base64_encode("$clientId:$clientSecret");
    $payload = http_build_query([
        'grant_type' => 'client_credentials',
        'scope'      => $scope
    ]);

    // build the curl request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        "Authorization: Basic $token"
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // process and return the response
    $response = curl_exec($ch);
    $response = json_decode($response, true);
    if (! isset($response['access_token'])
        || ! isset($response['token_type'])) {
        exit('failed, exiting.');
    }

    // here's your token to use in API requests
    return $response['access_token'];
}
