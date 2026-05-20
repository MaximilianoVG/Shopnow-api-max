<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->setBasePath('/serv_pedido.php'); 

$app->addBodyParsingMiddleware();

// Archivo donde guardaremos los pedido
$archivoDatos = __DIR__ . '/pedido.json';

// --- FUNCIONES PARA LEER Y GUARDAR ---

function leerpedido($archivo) {
    if (!file_exists($archivo)) {
        file_put_contents($archivo, json_encode([]));
    }
    $contenido = file_get_contents($archivo);
    return json_decode($contenido, true) ?: [];
}

function guardarpedido($archivo, $datos) {
    file_put_contents($archivo, json_encode($datos, JSON_PRETTY_PRINT));
}

/**
 * GET /pedido
 * Resumen: Obtener el historial de pedido
 */
$app->get('/pedido', function (Request $request, Response $response) use ($archivoDatos) {
    $db_pedido = leerpedido($archivoDatos);
    $response->getBody()->write(json_encode($db_pedido));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/**
 * POST /pedido
 * Resumen: Crear un nuevo pedido
 */
$app->post('/pedido', function (Request $request, Response $response) use ($archivoDatos) {
    $data = $request->getParsedBody();
    $db_pedido = leerpedido($archivoDatos);

    // 1. Verificar que vengan los datos obligatorios del contrato
    if (!isset($data['id_cliente'], $data['productos'], $data['total'])) {
        $response->getBody()->write(json_encode(["error" => "Error de Contrato: Faltan campos (id_cliente, productos, total)"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 2. Validación de tipos (cliente debe ser número, productos un arreglo, total un número mayor a 0)
    if (!is_int($data['id_cliente']) || !is_array($data['productos']) || !is_numeric($data['total']) || $data['total'] <= 0) {
        $response->getBody()->write(json_encode(["error" => "Error de Contrato: Formato o valores inválidos"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 3. Crear un ID de pedido automático
    $nuevo_id = 1;
    if (count($db_pedido) > 0) {
        $ids = array_column($db_pedido, 'id_pedido');
        $nuevo_id = max($ids) + 1;
    }

    // Armar el "ticket" del pedido 
    $nuevo_pedido = [
        "id_pedido" => $nuevo_id,
        "id_cliente" => $data['id_cliente'],
        "productos" => $data['productos'],
        "total" => $data['total'],
        "fecha_compra" => date('Y-m-d H:i:s')
    ];

    // Guardar en el archivo JSON
    $db_pedido[] = $nuevo_pedido;
    guardarpedido($archivoDatos, $db_pedido);

    $response->getBody()->write(json_encode([
        "mensaje" => "Pedido registrado exitosamente",
        "datos" => $nuevo_pedido
    ]));
    
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

$app->run();