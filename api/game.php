<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../src/BlackjackGame.php';
BlackjackGame::bootstrap();

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        respond(['ok' => true, 'state' => BlackjackGame::publicState()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['ok' => false, 'error' => 'Método no permitido.'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $token = (string) ($input['csrf'] ?? '');
    if (!isset($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], $token)) {
        respond(['ok' => false, 'error' => 'Sesión caducada. Recarga la página.'], 419);
    }

    $action = (string) ($input['action'] ?? '');
    $state = match ($action) {
        'start' => BlackjackGame::start((int) ($input['bet'] ?? 25)),
        'hit' => BlackjackGame::hit(),
        'stand' => BlackjackGame::stand(),
        'double' => BlackjackGame::double(),
        'split' => BlackjackGame::split(),
        'reset-bankroll' => BlackjackGame::resetBankroll(),
        default => throw new RuntimeException('Acción no reconocida.'),
    };

    respond(['ok' => true, 'state' => $state]);
} catch (RuntimeException $e) {
    respond(['ok' => false, 'error' => $e->getMessage(), 'state' => BlackjackGame::publicState()], 422);
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => 'Ha ocurrido un error inesperado.'], 500);
}
