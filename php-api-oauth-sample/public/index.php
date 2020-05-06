<?php
require __DIR__."/../bootstrap.php";

use Src\Controllers\CustomerController;
use \Firebase\JWT\JWT;
use \Firebase\JWT\JWK;

// send some CORS headers so the API can be called from anywhere
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER["REQUEST_METHOD"];
$uriParts = explode( '/', $uri );

// define all valid endpoints - this will act as a simple router
$routes = [
    'customers' => [
        'method' => 'GET',
        'expression' => '/^\/customers\/?$/',
        'controller_method' => 'index'
    ],
    'customers.create' => [
        'method' => 'POST',
        'expression' => '/^\/customers\/?$/',
        'controller_method' => 'store'
    ],
    'customers.charge' => [
        'method' => 'POST',
        'expression' => '/^\/customers\/(\d+)\/charges\/?$/',
        'controller_method' => 'charge'
    ]
];

$routeFound = null;
foreach ($routes as $route) {
    if ($route['method'] == $requestMethod &&
        preg_match($route['expression'], $uri))
    {
        $routeFound = $route;
        break;
    }
}

if (! $routeFound) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$methodName = $route['controller_method'];

// authenticate the request:
if (! authenticate($methodName)) {
    header("HTTP/1.1 401 Unauthorized");
    exit('Unauthorized');
}

$controller = new CustomerController();
$controller->$methodName($uriParts);

// END OF FRONT CONTROLLER
// OAuth authentication functions follow

function authenticate($methodName)
{
    // extract the token from the headers
    if (! isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return false;
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    preg_match('/Bearer\s(\S+)/', $authHeader, $matches);

    if(! isset($matches[1])) {
        return false;
    }

    $token = $matches[1];

    return authenticateRemotely($token);
}

function authenticateRemotely($token)
{
    $metadataUrl = getenv('OPENID_CONFIGURATION');
    $metadata = http($metadataUrl);
    $introspectionUrl = $metadata['introspection_endpoint'];

    $params = [
        'token' => $token,
        'client_id' => getenv('CLIENT_ID'),
        'client_secret' => getenv('CLIENT_SECRET')
    ];

    $result = http($introspectionUrl, $params);

    if (! $result['active']) {
        return false;
    }
    return true;
}

/**
 * This method is not a part of oauth2 
 * 
 * It is a token decode and introspection, based on JWT authentication.
 * 
 * IE for Google, this method will not run because Google does not have
 * JWT tokens on oauth2.
 * 
 * JWT tokens on oauth2 are not specified by IETF.
 */
function authenticateLocally($token, $tokenParts)
{
    $tokenParts = explode('.', $token);
    $decodedToken['header'] = json_decode(base64UrlDecode($tokenParts[0]), true);
    $decodedToken['payload'] = json_decode(base64UrlDecode($tokenParts[1]), true);
    $decodedToken['signatureProvided'] = base64UrlDecode($tokenParts[2]);

    // Get the JSON Web Keys from the server that signed the token
    // (ideally they should be cached to avoid
    // calls to Okta on each API request)...
    $metadataUrl = getenv('OPENID_CONFIGURATION');
    $metadata = http($metadataUrl);
    $jwksUri = $metadata['jwks_uri'];
    $keys = http($jwksUri);

    // Find the public key matching the kid from the input token
    $publicKey = false;
    foreach($keys['keys'] as $key) {
        if($key['kid'] == $decodedToken['header']['kid']) {
            $publicKey = JWK::parseKey($key);
            break;
        }
    }    
    if (!$publicKey) {
        echo "Couldn't find public key\n";
        return false;
    }

    // Check the signing algorithm
    if ($decodedToken['header']['alg'] != 'RS256') {
        echo "Bad algorithm\n";
        return false;
    }

    $result = JWT::decode($token, $publicKey, array('RS256'));

    if (! $result) {
        echo "Error decoding JWT\n";
        return false;
    }

    // Basic JWT validation passed, now check the claims

    // Verify the Issuer matches Okta's issuer
    $metadataUrl = getenv('OPENID_CONFIGURATION');
    $metadata = http($metadataUrl);
    $issuer = $metadata['issuer'];
    if ($decodedToken['payload']['iss'] != $issuer) {
        echo "Issuer did not match\n";
        return false;
    }

    // Verify the audience matches the expected audience for this API
    if ($decodedToken['payload']['aud'] != getenv('OKTA_AUDIENCE')) {
        echo "Audience did not match\n";
        return false;
    }

    // Verify this token was issued to the expected client_id
    if ($decodedToken['payload']['cid'] != getenv('CLIENT_ID')) {
        echo "Client ID did not match\n";
        return false;
    }

    return true;
}

function http($url, $params = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($params) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    return json_decode(curl_exec($ch), true);
}

function base64UrlDecode($input)
{
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $input .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($input, '-_', '+/'));
}

function encodeLength($length)
{
    if ($length <= 0x7F) {
        return chr($length);
    }
    $temp = ltrim(pack('N', $length), chr(0));
    return pack('Ca*', 0x80 | strlen($temp), $temp);
}

function base64UrlEncode($text)
{
    return str_replace(
        ['+', '/', '='],
        ['-', '_', ''],
        base64_encode($text)
    );
}
