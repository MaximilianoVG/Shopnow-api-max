<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->setBasePath('/serv_productos.php'); 
$app->addBodyParsingMiddleware();

// Archivo donde guardaremos los productos
$archivoDatos = __DIR__ . '/productos.json';

// --- FUNCIONES PARA LEER Y GUARDAR ---

function leerProductos($archivo) {
    if (!file_exists($archivo)) {
        file_put_contents($archivo, json_encode([]));
    }
    $contenido = file_get_contents($archivo);
    return json_decode($contenido, true) ?: [];
}

function guardarProductos($archivo, $datos) {
    file_put_contents($archivo, json_encode($datos, JSON_PRETTY_PRINT));
}

/**
 * GET /productos
 */
$app->get('/productos', function (Request $request, Response $response) use ($archivoDatos) {
    $db_productos = leerProductos($archivoDatos);
    $response->getBody()->write(json_encode($db_productos));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/**
 * POST /productos
 */
$app->post('/productos', function (Request $request, Response $response) use ($archivoDatos) {
    $data = $request->getParsedBody();
    $db_productos = leerProductos($archivoDatos);

    // 1. Verificar campos obligatorios
    if (!isset($data['nombre'], $data['precio'], $data['stock'])) {
        $response->getBody()->write(json_encode(["error" => "Error de Contrato: Faltan campos (nombre, precio, stock)"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 2. Validación de tipos y reglas de negocio
    if (!is_string($data['nombre']) || !is_numeric($data['precio']) || !is_int($data['stock']) || $data['precio'] <= 0 || $data['stock'] < 0) {
        $response->getBody()->write(json_encode(["error" => "Error de Contrato: Formato o valores inválidos"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 3. Validación: Evitar duplicados por nombre
    foreach ($db_productos as $producto) {
        if (strtolower($producto['nombre']) === strtolower($data['nombre'])) {
            $response->getBody()->write(json_encode([
                "error" => "Conflicto",
                "detalle" => "El producto '{$data['nombre']}' ya existe en el catálogo"
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    // 4. IDs automáticos
    $nuevo_id = 1;
    if (count($db_productos) > 0) {
        $ids = array_column($db_productos, 'id_producto');
        $nuevo_id = max($ids) + 1;
    }

    // Armar y guardar
    $nuevo_producto = [
        "id_producto" => $nuevo_id,
        "nombre" => $data['nombre'],
        "precio" => $data['precio'],
        "stock" => $data['stock']
    ];

    // Guardamos el nuevo producto en la base de datos (archivo JSON)
    $db_productos[] = $nuevo_producto;
    guardarProductos($archivoDatos, $db_productos);

    $response->getBody()->write(json_encode([
        "mensaje" => "Producto agregado exitosamente",
        "datos" => $nuevo_producto
    ]));
    
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

$app->run();