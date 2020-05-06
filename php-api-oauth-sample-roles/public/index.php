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
        'controller_method' => 'index',
        'role' => 'customer'
    ],
    'customers.create' => [
        'method' => 'POST',
        'expression' => '/^\/customers\/?$/',
        'controller_method' => 'store',
        'role' => 'customer'
    ],
    'customers.charge' => [
        'method' => 'POST',
        'expression' => '/^\/customers\/(\d+)\/charges\/?$/',
        'controller_method' => 'charge',
        'role' => 'customer'
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
$role = $route['role'];

// authenticate the request:
if (! authenticate($methodName, $role)) {
    header("HTTP/1.1 401 Unauthorized");
    exit('Unauthorized');
}

$controller = new CustomerController();
$controller->$methodName($uriParts);

// END OF FRONT CONTROLLER
// OAuth authentication functions follow

function authenticate($methodName, $role)
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

    return authenticateRemotely($token, $role);
}

function authenticateRemotely($token, $role)
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

    
    if(! in_array($role, $result['resource_access'][$params['client_id']]['roles']) ){
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
