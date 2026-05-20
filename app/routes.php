<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

// File-based database for persistence across requests
$db_file = __DIR__ . '/../var/db_clientes.json';

function get_clientes() {
    global $db_file;
    if (!file_exists($db_file)) {
        return [];
    }
    $data = file_get_contents($db_file);
    return json_decode($data, true) ?? [];
}

function save_clientes($clientes) {
    global $db_file;
    file_put_contents($db_file, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    /**
     * GET /clientes
     * Resumen: Obtener lista de clientes registrados
     */
    $app->get('/clientes', function (Request $request, Response $response) {
        $clientes = get_clientes();
        $payload = json_encode($clientes);
        $response->getBody()->write($payload);
        return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
    });

    /**
     * POST /clientes
     * Resumen: Registrar nuevo cliente
     */
    $app->post('/clientes', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        // Validar que $data sea un array
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(["error" => "Error: Cuerpo de solicitud inválido"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // --- VALIDACIONES DEL CONTRATO (Equivalente a Pydantic) ---
        
        // 1. Verificar que todos los campos obligatorios existan (sin id_cliente)
        if (!isset($data['nombre'], $data['correo'])) {
            $response->getBody()->write(json_encode(["error" => "Error de Contrato: Faltan campos obligatorios (nombre, correo)"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 2. Validación de tipos y longitudes (Regla de Oro)
        if (!is_string($data['nombre']) ||
            strlen($data['nombre']) < 3 || 
            strlen($data['nombre']) > 100 || 
            !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            
            $error = [
                "error" => "Error de Contrato",
                "detalle" => "Nombre debe tener 3-100 caracteres o correo inválido"
            ];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Obtener clientes actuales
        $clientes = get_clientes();
        
        // Calcular el siguiente ID
        $next_id = 1;
        if (!empty($clientes)) {
            $max_id = max(array_column($clientes, 'id_cliente'));
            $next_id = $max_id + 1;
        }
        
        // Crear nuevo cliente con ID autogenerado
        $nuevo_cliente = [
            "id_cliente" => $next_id,
            "nombre" => $data['nombre'],
            "correo" => $data['correo']
        ];
        $clientes[] = $nuevo_cliente;
        
        // Guardar en archivo
        save_clientes($clientes);

        $response->getBody()->write(json_encode([
            "mensaje" => "Cliente registrado exitosamente",
            "datos" => $nuevo_cliente
        ]));
        
        return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201); // 201 Created es estándar para POST exitosos
    });
};