<?php

declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');

spl_autoload_register(function ($class) {
    require __DIR__ . "/src/{$class}.php";
});

function sendJsonResponse(int $statusCode, array $data): void
{
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, [
        'error' => 'Method Not Allowed',
        'message' => 'Only POST requests are accepted'
    ]);
}

$parts = explode("/", $_SERVER['REQUEST_URI']);
$parts = array_filter($parts);
$parts = array_values($parts);

$validRoutes = [
    'bayut-whatsapp',
    'dubizzle-whatsapp',
];

$route = null;
foreach ($validRoutes as $validRoute) {
    $index = array_search($validRoute, $parts);
    if ($index !== false) {
        $route = $validRoute;
        break;
    }
}

if ($route === null) {
    sendJsonResponse(404, [
        'error' => 'Not Found',
        'message' => 'The requested endpoint does not exist'
    ]);
}

try {
    $controller = new WebhookController();
    $controller->handleRequest($route);
} catch (Throwable $e) {
    error_log("Error processing request: " . $e->getMessage());
    sendJsonResponse(500, [
        'error' => 'Internal Server Error',
        'message' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
