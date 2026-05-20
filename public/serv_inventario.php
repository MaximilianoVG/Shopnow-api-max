<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

// Cargamos las herramientas
require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Ajustar la ruta
$app->setBasePath('/serv_inventario.php'); 
$app->addBodyParsingMiddleware();

// ¡OJO AQUÍ! Usamos el archivo de productos porque ahí está guardado el "stock"
$archivoProductos = __DIR__ . '/productos.json';

// --- FUNCIONES PARA LEER Y GUARDAR ---
function leerProductos($archivo) {
    if (!file_exists($archivo)) {
        return [];
    }
    return json_decode(file_get_contents($archivo), true) ?: [];
}

function guardarProductos($archivo, $datos) {
    file_put_contents($archivo, json_encode($datos, JSON_PRETTY_PRINT));
}

// --- RUTAS DE LA API ---

/**
 * GET /inventario
 * Resumen: Ver un reporte rápido de existencias
 */
$app->get('/inventario', function (Request $request, Response $response) use ($archivoProductos) {
    $db_productos = leerProductos($archivoProductos);
    
    // Filtramos para mostrar solo la información relevante para inventario
    $reporte = array_map(function($prod) {
        return [
            "id_producto" => $prod['id_producto'],
            "nombre" => $prod['nombre'],
            "stock_actual" => $prod['stock']
        ];
    }, $db_productos);

    $response->getBody()->write(json_encode($reporte));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/**
 * PUT /inventario
 * Resumen: Actualizar la cantidad de stock de un producto
 */
$app->put('/inventario', function (Request $request, Response $response) use ($archivoProductos) {
    $data = $request->getParsedBody();
    $db_productos = leerProductos($archivoProductos);

    // 1. Validar contrato
    if (!isset($data['id_producto'], $data['nuevo_stock'])) {
        $response->getBody()->write(json_encode(["error" => "Faltan campos (id_producto, nuevo_stock)"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 2. Validar que el stock sea un número válido y no negativo
    if (!is_int($data['id_producto']) || !is_int($data['nuevo_stock']) || $data['nuevo_stock'] < 0) {
        $response->getBody()->write(json_encode(["error" => "Valores inválidos. El stock debe ser un número entero positivo."]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $producto_encontrado = false;
    
    // 3. Buscar el producto y modificar su stock
    foreach ($db_productos as &$producto) { // El símbolo '&' permite modificar el arreglo original directamente
        if ($producto['id_producto'] === $data['id_producto']) {
            $producto['stock'] = $data['nuevo_stock'];
            $producto_encontrado = true;
            break;
        }
    }

    // 4. Si nos mandan un ID que no existe
    if (!$producto_encontrado) {
        $response->getBody()->write(json_encode(["error" => "El producto no existe en la base de datos"]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    // Guardar los cambios en el archivo
    guardarProductos($archivoProductos, $db_productos);

    $response->getBody()->write(json_encode([
        "mensaje" => "Inventario actualizado exitosamente"
    ]));
    
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->run();
