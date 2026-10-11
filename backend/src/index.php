<?php
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

function conectarDB(): PDO
{
    $dsn = "mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT')
        . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4";
    return new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function conectarRedis(): Redis
{
    $redis = new Redis();
    $redis->connect(getenv('REDIS_HOST'), (int) getenv('REDIS_PORT'));
    return $redis;
}
function enviarCorreo(string $asunto, string $cuerpo): void
{
    $socket = fsockopen(getenv('SMTP_HOST'), (int) getenv('SMTP_PORT'), $errno, $errstr, 5);
    if (!$socket) {
        throw new RuntimeException("SMTP no disponible: $errstr");
    }

    // Lee una respuesta completa (las líneas "250-..." continúan, la "250 ..." termina)
    $leer = function () use ($socket) {
        $resp = '';
        while (($linea = fgets($socket, 512)) !== false) {
            $resp .= $linea;
            if (isset($linea[3]) && $linea[3] === ' ')
                break;
        }
        return $resp;
    };
    $enviar = function (string $cmd) use ($socket, $leer) {
        fwrite($socket, $cmd . "\r\n");
        return $leer();
    };

    $leer();                                   // saludo del servidor
    $enviar('EHLO backend');
    $enviar('MAIL FROM:<biblioteca@ejemplo.local>');
    $enviar('RCPT TO:<admin@ejemplo.local>');
    $enviar('DATA');

    $mensaje = "From: Biblioteca <biblioteca@ejemplo.local>\r\n"
        . "To: admin@ejemplo.local\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
        . $cuerpo . "\r\n.";              // el punto solo en una línea cierra el mensaje
    $enviar($mensaje);
    $enviar('QUIT');
    fclose($socket);
}

if ($ruta === 'health') {
    try {
        conectarDB()->query('SELECT 1');
        conectarRedis()->ping();
        echo json_encode(['status' => 'ok', 'db' => 'ok', 'redis' => 'ok']);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'detalle' => $e->getMessage()]);
    }
    exit;
}
if ($ruta === 'libros/export' && $metodo === 'GET') {
    try {
        $filas = conectarDB()
            ->query('SELECT * FROM libros ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Disposition: attachment; filename="libros.json"');
        echo json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['error' => 'Servicio no disponible']);
    }
    exit;
}


if ($ruta === 'libros' && $metodo === 'GET') {
    try {
        $redis = conectarRedis();
        $redis->incr('contador_consultas');

        $cache = $redis->get('libros:lista');
        if ($cache !== false) {
            header('X-Cache: HIT');
            echo $cache;
            exit;
        }

        $filas = conectarDB()->query('SELECT * FROM libros ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);
        $json = json_encode($filas);
        $redis->setex('libros:lista', 30, $json);

        header('X-Cache: MISS');
        echo $json;
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['error' => 'Servicio no disponible']);
    }
    exit;
}
if ($ruta === 'libros' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);

    $titulo = trim($datos['titulo'] ?? '');
    $autor = trim($datos['autor'] ?? '');
    $isbn = trim($datos['isbn'] ?? '');
    $anio = (int) ($datos['anio_publicacion'] ?? 0);

    if ($titulo === '' || $autor === '' || $isbn === '' || $anio < 1000 || $anio > 2100) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }

    try {
        $db = conectarDB();
        $stmt = $db->prepare(
            'INSERT INTO libros (titulo, autor, isbn, anio_publicacion) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$titulo, $autor, $isbn, $anio]);
        $id = (int) $db->lastInsertId();



        conectarRedis()->del('libros:lista');

        try {
            enviarCorreo(
                "Nuevo libro registrado: $titulo",
                "Se registró un libro.\nTítulo: $titulo\nAutor: $autor\nISBN: $isbn\nAño: $anio"
            );
        } catch (Throwable $e) {
            error_log('No se pudo enviar el correo: ' . $e->getMessage());
        }

        http_response_code(201);
        echo json_encode(['id' => $id, 'mensaje' => 'Libro creado']);
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            http_response_code(409);
            echo json_encode(['error' => 'El ISBN ya existe']);
        } else {
            http_response_code(503);
            echo json_encode(['error' => 'Servicio no disponible']);
        }
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['error' => 'Servicio no disponible']);
    }
    exit;
}
if (preg_match('#^libros/(\d+)$#', $ruta, $m) && $metodo === 'DELETE') {
    $id = (int) $m[1];
    try {
        $db = conectarDB();
        $stmt = $db->prepare('DELETE FROM libros WHERE id = ?');
        $stmt->execute([$id]);

        // CAMBIO: si no se borró ninguna fila, el libro no existía
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Libro no encontrado']);
            exit;
        }

        conectarRedis()->del('libros:lista');
        http_response_code(200);
        echo json_encode(['mensaje' => 'Libro eliminado']);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['error' => 'Servicio no disponible']);
    }
    exit;
}
if (preg_match('#^libros/(\d+)$#', $ruta, $m) && $metodo === 'PUT') {
    $id = (int) $m[1];
    $datos = json_decode(file_get_contents('php://input'), true);

    $titulo = trim($datos['titulo'] ?? '');
    $autor = trim($datos['autor'] ?? '');
    $isbn = trim($datos['isbn'] ?? '');
    $anio = (int) ($datos['anio_publicacion'] ?? 0);
    $estado = $datos['estado'] ?? '';

    // CAMBIO 1: validación antes de tocar la base de datos
    if (
        $titulo === '' || $autor === '' || $isbn === '' ||
        $anio < 1000 || $anio > 2100 ||
        !in_array($estado, ['disponible', 'prestado'], true)
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }

    try {
        $db = conectarDB();

        // CAMBIO 2: comprobar que el libro existe
        $existe = $db->prepare('SELECT id FROM libros WHERE id = ?');
        $existe->execute([$id]);
        if (!$existe->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Libro no encontrado']);
            exit;
        }

        $stmt = $db->prepare(
            'UPDATE libros SET titulo = ?, autor = ?, isbn = ?, anio_publicacion = ?, estado = ? WHERE id = ?'
        );
        $stmt->execute([$titulo, $autor, $isbn, $anio, $estado, $id]);

        conectarRedis()->del('libros:lista');

        echo json_encode(['mensaje' => 'Libro actualizado']);
    } catch (PDOException $e) {
        // CAMBIO 3: distinguir ISBN duplicado (1062) de otros errores
        if (($e->errorInfo[1] ?? null) == 1062) {
            http_response_code(409);
            echo json_encode(['error' => 'El ISBN ya existe']);
        } else {
            http_response_code(503);
            echo json_encode(['error' => 'Servicio no disponible']);
        }
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['error' => 'Servicio no disponible']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);