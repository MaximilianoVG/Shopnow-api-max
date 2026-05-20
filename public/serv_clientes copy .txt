<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->setBasePath('/serv_clientes.php');
$app->addBodyParsingMiddleware();

$archivoDatos = __DIR__ . '/clientes.json';


// --- FUNCIONES PARA LEER Y GUARDAR EN JSON ---

function leerClientes($archivo) {
    // Si no existe, lo creamos vacío
    if (!file_exists($archivo)) {
        file_put_contents($archivo, json_encode([]));
    }
    $contenido = file_get_contents($archivo);
    return json_decode($contenido, true) ?: [];
}

function guardarClientes($archivo, $datos) {
    file_put_contents($archivo, json_encode($datos, JSON_PRETTY_PRINT));
}


/* GET /clientes
 */
$app->get('/clientes', function (Request $request, Response $response) use ($archivoDatos) {
    $db_clientes = leerClientes($archivoDatos);

    $response->getBody()->write(json_encode($db_clientes));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});


/**
 * POST /clientes
 */
$app->post('/clientes', function (Request $request, Response $response) use ($archivoDatos) {

    $data = $request->getParsedBody();
    $db_clientes = leerClientes($archivoDatos);

    // Validar campos obligatorios
    if (!isset($data['nombre'], $data['correo'])) {
        $response->getBody()->write(json_encode([
            "error" => "Error de Contrato: Faltan campos obligatorios (nombre, correo)"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Validación de formato
    if (strlen($data['nombre']) < 3 || 
        strlen($data['nombre']) > 100 || 
        !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {

        $response->getBody()->write(json_encode([
            "error" => "Error de Contrato",
            "detalle" => "Formato de nombre o correo inválido"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Validar duplicado por correo
    foreach ($db_clientes as $cliente) {
        // Aseguramos que existe la llave correo antes de compararla
        if (isset($cliente['correo']) && strtolower($cliente['correo']) === strtolower($data['correo'])) {
            $response->getBody()->write(json_encode([
                "error" => "Conflicto",
                "detalle" => "El correo '{$data['correo']}' ya está registrado"
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    // Generar ID incremental
    $nuevo_id = 1;
    if (count($db_clientes) > 0) {
        $ids = array_column($db_clientes, 'id_cliente');
        if (!empty($ids)) {
            $nuevo_id = max($ids) + 1;
        }
    }

    $nuevo_cliente = [
        "id_cliente" => $nuevo_id,
        "nombre" => $data['nombre'],
        "correo" => $data['correo'],
        "telefono" => $data['telefono'] ?? "",
        "direccion" => $data['direccion'] ?? ""
    ];

    // Agregar a la lista y guardar
    $db_clientes[] = $nuevo_cliente;
    guardarClientes($archivoDatos, $db_clientes);

    // Respuesta de éxito
    $response->getBody()->write(json_encode([
        "mensaje" => "Cliente registrado exitosamente",
        "datos" => $nuevo_cliente
    ]));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(201);
});

$app->run();