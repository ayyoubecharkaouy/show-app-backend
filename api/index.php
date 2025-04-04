<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/shows.php';
require_once __DIR__ . '/functions/upload.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Récupération du chemin de la requête
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Extraction du chemin après '/api'
$path = substr($request_uri, strpos($request_uri, '/api') + 4);
$path = trim($path, '/');
$segments = explode('/', $path);

// Routeur principal
$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch (strtoupper($_SERVER['REQUEST_METHOD'])) {
        case 'POST':
            if ($endpoint === 'login') {
                loginUser($pdo);
            } elseif ($endpoint === 'shows') {
                checkAuth(); // Protection de la route
                createShow($pdo);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        case 'GET':
            if ($endpoint === 'shows') {
                if ($id) {
                    getShow($pdo, $id); // Pas de protection pour la lecture individuelle
                } else {
                    getAllShows($pdo); // Pas de protection pour la liste
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        case 'PUT':
            if ($endpoint === 'shows' && $id) {
                checkAuth(); // Protection de la route
                updateShow($pdo, $id);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        case 'DELETE':
            if ($endpoint === 'shows' && $id) {
                checkAuth(); // Protection de la route
                deleteShow($pdo, $id);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
            
        case 'OPTIONS':
            // Réponse pour les requêtes CORS preflight
            http_response_code(200);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>