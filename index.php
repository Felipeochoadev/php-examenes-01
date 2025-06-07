<?php
    header('Content-Type: application/json');

    //ESTE FRAGMENTO ES PARA AMBIENTE DE DESARROLLO LOCAL
    if (strpos($_SERVER['REQUEST_URI'], '/.well-known') === 0) {
        http_response_code(204);
        exit;
    }

    function obtenerIpCliente() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
    
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Si hay múltiples IPs, tomamos la primera
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
    
        return $_SERVER['REMOTE_ADDR'] ?? 'IP no disponible';
    }

    function EnviarRespuesta($Codigo, $Estado, $Mensaje, $Datos = null) {
        http_response_code($Codigo);
        $Respuesta = ['estado' => $Estado, 'mensaje' => $Mensaje];
        if ($Datos !== null) {
            $Respuesta['datos'] = $Datos;
        }
        echo json_encode($Respuesta);
    }

    function ConexionDb($tipo = 'sqlite') {
        try { 
            switch ($tipo) {
                case 'sqlite':
                    $db = new PDO('sqlite:examenes.db');
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    return $db;
                break;
                default:
                    EnviarRespuesta(400, 'error', 'Base de datos desconocida', $tipo);
                    error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: Base de datos Desconocida ::::: ".$tipo."" );
		            exit; 
                break;
            }
        } catch (PDOException $e) {
            EnviarRespuesta(500, 'error', 'Fallo conexion a base de datos' . $e->getMessage());
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: Fallo conexion a base de datos ::::: ".$e->getMessage()."" );
            exit;
        }
    }

    function CrearBD() {
        $db = ConexionDb();
        $db->exec("
            CREATE TABLE IF NOT EXISTS areas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                materia TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS pruebas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_area INTEGER NOT NULL,
                titulo TEXT NOT NULL,
                fecha DATE,
                FOREIGN KEY (id_area) REFERENCES areas(id)
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS preguntas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_prueba INTEGER NOT NULL,
                enunciado TEXT NOT NULL,
                FOREIGN KEY (id_prueba) REFERENCES pruebas(id)
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS opciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_pregunta INTEGER NOT NULL,
                texto TEXT NOT NULL,
                resultado INTEGER DEFAULT 0,
                FOREIGN KEY (id_pregunta) REFERENCES preguntas(id)
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS estudiantes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identificacion INTEGER NOT NULL UNIQUE,
                nombre TEXT NOT NULL
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS resultados (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_estudiante INTEGER NOT NULL,
                id_prueba INTEGER NOT NULL,
                calificacion REAL,
                fecha DATE,
                FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id),
                FOREIGN KEY (id_prueba) REFERENCES pruebas(id)
            );
        ");

        $CantAreas = $db->query("SELECT COUNT(*) FROM areas");
        if ($CantAreas->fetchColumn() == 0) {
            $db->exec("
                INSERT INTO areas (materia) VALUES
                ('Matemáticas'),
                ('Lenguaje'),
                ('Inglés'),
                ('Ciencias Naturales'),
                ('Competencias Ciudadanas')
            ");

            $db->exec("
                INSERT INTO pruebas (id_area, titulo, fecha) VALUES
                (1, 'Prueba de Aritmética Básica', '2025-06-01'),
                (1, 'Prueba de Geometría Básica', '2025-06-02'),
                (1, 'Prueba de Problemas Matemáticos', '2025-06-03'),
                (2, 'Prueba de Ortografía', '2025-06-04'),
                (2, 'Prueba de Comprensión Lectora', '2025-06-05'),
                (2, 'Prueba de Gramática', '2025-06-06'),
                (3, 'Prueba de Vocabulario Básico', '2025-06-07'),
                (3, 'Prueba de Comprensión Auditiva', '2025-06-08'),
                (3, 'Prueba de Gramática Básica', '2025-06-09'),
                (4, 'Prueba de Biología Básica', '2025-06-10'),
                (4, 'Prueba de Ciencias de la Tierra', '2025-06-11'),
                (4, 'Prueba de Física Básica', '2025-06-12'),
                (5, 'Prueba de Valores y Ética', '2025-06-13'),
                (5, 'Prueba de Derechos y Responsabilidades', '2025-06-14'),
                (5, 'Prueba de Participación Ciudadana', '2025-06-15')
            ");
        }
    }

    function InicializarBD() {
        $db = ConexionDb();
        $TablaNombre = 'resultados';
        $ValidarExistencia = $db->query("
            SELECT name 
            FROM sqlite_master 
            WHERE type='table' AND name='$TablaNombre'
        ");

        if (!$ValidarExistencia->fetch()) {
            CrearBD();
        }
    }

    function ObtenerPruebas($area = null) {
        $db = ConexionDb();
        if($area){
            $query = "
                SELECT *
                FROM pruebas
                WHERE id_area = :area
            ";
            $params = [':area' => $area];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $query = "
                SELECT * FROM areas
            ";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        EnviarRespuesta(200, 'success', 'Listado correctamente', $results);
    }

    function handleRequest() {
        $Metodo = $_SERVER['REQUEST_METHOD'];
        $Ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $RutaObjeto = explode('/', trim($Ruta, '/'));
        $Parametros = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($Parametros ?? '', $ArrayParametros);

        InicializarBD();

        $RutaEsperada = array(
            'Ruta0' => array(
                'api' => true
            ), 
            'Ruta1' => array(
                'pruebas' => true,
                'preguntas' => true
            ), 
        );

        if ( !isset($RutaEsperada['Ruta0'][$RutaObjeto[0]]) || !isset($RutaEsperada['Ruta1'][$RutaObjeto[1]]) ){
            EnviarRespuesta(404, 'AccessDenied', 'Access Denied', array( 'ip' => obtenerIpCliente() ) );
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: Ruta del EndPoint inesperada  ::::: Recibida: ".json_encode($RutaObjeto)." ::::: Esperada: ".json_encode($RutaEsperada)."" );
		    exit;
        }

        switch ($RutaObjeto[0]) {
            case 'api':
                switch ($RutaObjeto[1]) {
                    case 'pruebas':
                        switch ($Metodo) {
                            case 'GET':
                                $area = $ArrayParametros['area'] ?? null;
                                ObtenerPruebas($area);
                                exit;
                            break;
                            case 'POST':
                                
                                exit;
                            break;
                            case 'PUT':
                                
                                exit;
                            break;
                            case 'DELETE':
                                
                                exit;
                            break;
                        }
                    break;
                    case 'preguntas':
                        switch ($Metodo) {
                            case 'GET':
                                
                                exit;
                            break;
                            case 'POST':
                                
                                exit;
                            break;
                            case 'PUT':
                                
                                exit;
                            break;
                            case 'DELETE':
                                
                                exit;
                            break;
                        }
                    break;
                }
            break;
        }

        EnviarRespuesta(404, 'error', 'EndPoint desconocido', $Metodo );
        error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: EndPoint desconocido ::::: ".$Metodo."" );
        exit;

    }

    try {
        handleRequest();
    } catch (Exception $e) {
        EnviarRespuesta(500, 'error', 'Server error: ' . $e->getMessage());
    }
?>