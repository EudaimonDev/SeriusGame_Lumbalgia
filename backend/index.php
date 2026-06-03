<?php
// Carga .env si existe (local), si no usa variables de entorno del sistema (Railway)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
} else {
    // Railway: cargar desde variables de entorno del sistema
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'JWT_SECRET', 'GEMINI_API_KEY'] as $key) {
        if (getenv($key) !== false) {
            $_ENV[$key] = getenv($key);
        }
    }
}


require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

$router = new Router();

// --- Auth estudiante (pública) ---
$router->post('/api/auth/student',     'AuthController@studentRegister');

// --- Auth admin (pública) ---
$router->post('/api/auth/admin/login', 'AuthController@adminLogin');
$router->post('/api/auth/forgot-password',      'AuthController@forgotPassword'); 

// --- Config del juego (pública) ---
$router->get('/api/config',        'GameConfigController@get');

// --- Ranking (pública) ---
$router->get('/api/ranking',       'RankingController@index');
$router->post('/api/rooms/join', 'RoomController@join');

// --- Admin: config ---
$router->get('/api/admin/config',  'GameConfigController@get',    ['auth', 'admin']);
$router->put('/api/admin/config',  'GameConfigController@update', ['auth', 'admin']);
$router->get('/api/admin/stats', 'AdminController@stats', ['auth', 'admin']);
// --- Admin: categorías ---
$router->post  ('/api/admin/categories',        'AdminController@storeCategory',   ['auth', 'admin']);
$router->put   ('/api/admin/categories/{id}',   'AdminController@updateCategory',  ['auth', 'admin']);
$router->delete('/api/admin/categories/{id}',   'AdminController@destroyCategory', ['auth', 'admin']);
// --- Admin: gestión de salas ---
$router->get   ('/api/admin/rooms',                'RoomController@index',   ['auth', 'admin']);
$router->post  ('/api/admin/rooms',                'RoomController@store',   ['auth', 'admin']);
$router->patch ('/api/admin/rooms/{id}/toggle',    'RoomController@toggle',  ['auth', 'admin']);
$router->delete('/api/admin/rooms/{id}',           'RoomController@destroy', ['auth', 'admin']);
$router->patch('/api/admin/rooms/{id}/phase', 'RoomController@updatePhase', ['auth', 'admin']);
$router->put('/api/admin/rooms/{id}', 'RoomController@update', ['auth', 'admin']);

// --- Admin: reportes ---
$router->get('/api/admin/reports/rooms',    'AdminController@reportRooms',    ['auth', 'admin']);
$router->get('/api/admin/reports/students', 'AdminController@reportStudents', ['auth', 'admin']);
$router->get('/api/admin/reports/evolution', 'AdminController@reportEvolution', ['auth', 'admin']);
$router->get('/api/admin/reports/stats',     'AdminController@reportStats',     ['auth', 'admin']);
$router->get('/api/admin/reports/testcomparison', 'AdminController@reportTestComparison', ['auth', 'admin']);
$router->get('/api/admin/reports/questions', 'AdminController@reportQuestions', ['auth', 'admin']);
// --- Mantener compatibilidad ---
$router->post('/api/auth/register',    'AuthController@studentRegister');
$router->post('/api/auth/login',       'AuthController@adminLogin');

// --- Perfil (requiere auth) ---
$router->get('/api/profile', 'ProfileController@me', ['auth']);

// --- Juego (requiere auth) ---
$router->post('/api/game/start',  'GameController@start',  ['auth']);
$router->post('/api/game/answer', 'GameController@answer', ['auth']);
$router->get('/api/game/result',  'GameController@result', ['auth']);

// --- Admin: gestión de preguntas ---
$router->get   ('/api/admin/questions',                'AdminController@index',      ['auth', 'admin']);
$router->post  ('/api/admin/questions/import',         'AdminController@import',     ['auth', 'admin']);
$router->post  ('/api/admin/questions/generate',       'AdminController@generate',   ['auth', 'admin']);
$router->post  ('/api/admin/questions',                'AdminController@store',      ['auth', 'admin']);
$router->patch ('/api/admin/questions/{id}/toggle',    'AdminController@toggle',     ['auth', 'admin']);
$router->put   ('/api/admin/questions/{id}',           'AdminController@update',     ['auth', 'admin']);
$router->delete('/api/admin/questions/{id}',           'AdminController@destroy',    ['auth', 'admin']);
$router->get   ('/api/admin/categories',               'AdminController@categories', ['auth', 'admin']);

// --- Reportes ---
$router->get('/api/results/me',    'ResultController@myResults',   ['auth']);
$router->get('/api/results/group', 'ResultController@groupReport', ['auth', 'admin']);

$router->dispatch();
