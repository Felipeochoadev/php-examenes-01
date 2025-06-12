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
                materia TEXT UNIQUE NOT NULL,
                descripcion TEXT NOT NULL
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
                INSERT INTO areas (materia, descripcion) VALUES
                ('matematicas', 'Matemáticas'),
                ('lenguaje', 'Lenguaje'),
                ('ingles', 'Inglés'),
                ('ciencias naturales', 'Ciencias Naturales'),
                ('competencias ciudadanas', 'Competencias Ciudadanas')
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

    function ValidarCampos(array $campos, array $reglas) {
        $camposNoPermitidos = array_diff_key($campos, array_flip(array_keys($reglas)));
        if (!empty($camposNoPermitidos)) {
            EnviarRespuesta(400, 'error', 'Campos no permitidos', $camposNoPermitidos);
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: Campos no permitidos ::::: ".json_encode( $camposNoPermitidos )."" );
            exit;
        }
        $errores = [];
        foreach ($reglas as $campo => $regla) {
            $valor = $campos[$campo] ?? null;
    
            if (($regla['requerido'] ?? false) && (is_null($valor) || trim($valor) === '')) {
                $errores[$campo][] = 'Campo requerido';
                continue;
            }
    
            if (is_null($valor) || trim($valor) === '') {
                continue;
            }
    
            if (isset($regla['tipo'])) {
                switch ($regla['tipo']) {
                    case 'texto':
                        if (!is_string($valor)) {
                            $errores[$campo][] = 'Debe ser texto';
                        }
                    break;
                    case 'numerico':
                        if (!is_numeric($valor)) {
                            $errores[$campo][] = 'Debe ser numerico';
                        }
                    break;
                    case 'entero':
                        if (filter_var($valor, FILTER_VALIDATE_INT) === false) {
                            $errores[$campo][] = 'Debe ser un numero entero';
                        }
                    break;
                    case 'float':
                        if (filter_var($valor, FILTER_VALIDATE_FLOAT) === false) {
                            $errores[$campo][] = 'Debe ser un numero decimal';
                        }
                    break;
                    case 'email':
                        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                            $errores[$campo][] = 'Formato de email invalido';
                        }
                    break;
                    case 'booleano':
                        if (!in_array($valor, [true, false, 0, 1, "0", "1"], true)) {
                            $errores[$campo][] = 'Debe ser un valor booleano';
                        }
                    break;
                    case 'date':
                        if (!DateTime::createFromFormat('Y-m-d', $valor)) {
                            $errores[$campo][] = 'Fecha invalida (formato esperado: YYYY-MM-DD)';
                        }
                    break;
                    case 'time':
                        if (!DateTime::createFromFormat('H:i:s', $valor)) {
                            $errores[$campo][] = 'Hora invalida (formato esperado: HH:MM:SS)';
                        }
                    break;
                    case 'datetime':
                        if (!DateTime::createFromFormat('Y-m-d H:i:s', $valor)) {
                            $errores[$campo][] = 'Fecha y hora invalidas (formato esperado: YYYY-MM-DD HH:MM:SS)';
                        }
                    break;
                }
            }
    
            if (isset($regla['min']) && is_string($valor) && strlen($valor) < $regla['min']) {
                $errores[$campo][] = "Debe tener al menos {$regla['min']} caracteres";
            }
    
            if (isset($regla['max']) && is_string($valor) && strlen($valor) > $regla['max']) {
                $errores[$campo][] = "Debe tener maximo {$regla['max']} caracteres";
            }
    
            if (isset($regla['regex']) && !preg_match($regla['regex'], $valor)) {
                $errores[$campo][] = 'Formato invalido';
            }
    
            if (isset($regla['enum']) && !in_array($valor, $regla['enum'], true)) {
                $errores[$campo][] = 'Valor no permitido';
            }
        }
    
        if (!empty($errores)) {
            EnviarRespuesta(400, 'error', 'Errores de validacion', $errores);
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: Errores de validacion ::::: ".json_encode( $errores )."" );
            exit;
        }
    }

    function normalizarTexto($texto) {
        $texto = strtolower(trim($texto));
        $texto = strtr(
            $texto, [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            ]
        );
        return $texto;
    }
    
    function ObtenerPruebas($materia = null) {
        $materia = normalizarTexto( $materia );
        $db = ConexionDb();
        if($materia){
            $query = "
                SELECT *
                FROM pruebas as p
                JOIN areas as a ON a.id = p.id_area
                WHERE a.materia = :materia
            "; 
            $params = [
                ':materia' => $materia
            ];
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

    function CrearArea($campo) {
        $reglas = [
            'materia' => [
                'requerido' => true,
                'tipo' => 'texto',
            ]
        ];
        ValidarCampos($campo, $reglas);
        $materia = normalizarTexto( $campo['materia'] );
        try {
            $db = ConexionDb();
            $query = "
                SELECT 1 
                FROM areas 
                WHERE materia = :materia
                LIMIT 1
            ";
            $params = [
                ':materia' => $materia
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $existe = $stmt->fetchColumn();
            if ($existe) {
                EnviarRespuesta(400, 'error', 'El registro ya existe');
                exit;
            }
            $query = "
                INSERT INTO areas (materia, descripcion)
                VALUES (:materia, :descripcion)
            ";
            $params = [
                ':materia' => $materia, 
                ':descripcion' => $campo['materia']
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            EnviarRespuesta(201, 'success', 'Registro creado correctamente');
        } catch (PDOException $e) {
            EnviarRespuesta(500, 'success', 'error', 'No se pudo crear' . $e->getMessage());
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: No se pudo crear ::::: ".$e->getMessage()."" );
        }
    }

    function ActualizarArea($campo) {
        $Requeridos = array("id", "materia");
        $reglas = [
            'id' => [
                'requerido' => true,
                'tipo' => 'entero',
            ],
            'materia' => [
                'tipo' => 'texto',
                'requerido' => true
            ]
        ];
        ValidarCampos($campo, $reglas);
        $materia = normalizarTexto( $campo['materia'] );
        try {
            $db = ConexionDb();
            //validar si existe el registro del id
            $query = "
                SELECT 1 
                FROM areas 
                WHERE id = :id
                LIMIT 1
            ";
            $params = [
                ':id' => $campo['id']
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $existe = $stmt->fetchColumn();
            if (!$existe) {
                EnviarRespuesta(400, 'error', 'No existe el registro');
                exit;
            }

            //validar si el update no esta exista para evitar duplicado
            $query = "
                SELECT 1 
                FROM areas 
                WHERE materia = :materia
                LIMIT 1
            ";
            $params = [
                ':materia' => $materia
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $existe = $stmt->fetchColumn();
            if ($existe) {
                EnviarRespuesta(400, 'error', 'El registro ya existe');
                exit;
            }
            
            //actualizar registro
            $query = "
                UPDATE areas
                SET materia = :materia, descripcion = :descripcion
                WHERE id = :id
            ";
            $params = [
                ':id' => $campo['id'], 
                ':materia' => $materia, 
                ':descripcion' => $campo['materia']
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            EnviarRespuesta(201, 'success', 'Registro actualizado correctamente');
        } catch (PDOException $e) {
            EnviarRespuesta(500, 'success', 'error', 'No se pudo actualizar' . $e->getMessage());
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: No se pudo actualizar ::::: ".$e->getMessage()."" );
        }
    }

    function EliminarArea($campo) {
        $Requeridos = array("id");
        $reglas = [
            'id' => [
                'requerido' => true,
                'tipo' => 'entero',
            ]
        ];
        ValidarCampos($campo, $reglas);
        try {
            $db = ConexionDb();
            //validar si existe el registro del id
            $query = "
                SELECT 1 
                FROM areas 
                WHERE id = :id
                LIMIT 1
            ";
            $params = [
                ':id' => $campo['id']
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $existe = $stmt->fetchColumn();
            if (!$existe) {
                EnviarRespuesta(400, 'error', 'No existe el registro');
                exit;
            }

            //eliminar registro
            $query = "
                DELETE FROM areas WHERE id = :id
            ";
            $params = [
                ':id' => $campo['id']
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            EnviarRespuesta(201, 'success', 'Registro eliminado correctamente');
        } catch (PDOException $e) {
            EnviarRespuesta(500, 'success', 'error', 'No se pudo actualizar' . $e->getMessage());
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: No se pudo actualizar ::::: ".$e->getMessage()."" );
        }
    }

    function handleRequest() {
        $Metodo = $_SERVER['REQUEST_METHOD'];

        // Obtener ruta y dividirla en segmentos
        $Ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $RutaObjeto = explode('/', trim($Ruta, '/'));

        // Obtener parámetros de la URL
        $Parametros = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($Parametros ?? '', $ParametrosGET);
        if( $Metodo == 'POST' || $Metodo == 'PUT' || $Metodo == 'DELETE'){
            $ParametrosPost = json_decode(file_get_contents('php://input'), true);
            if (!$ParametrosPost) {
                EnviarRespuesta(400, 'error', 'Campo JSON invalido');
                exit;
            }
        }
        
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
            EnviarRespuesta(404, 'Acceso denegado', 'Acceso denegado');
            error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: Ruta del EndPoint inesperada  ::::: Recibida: ".json_encode($RutaObjeto)." ::::: Esperada: ".json_encode($RutaEsperada)."" );
		    exit;
        }

        switch ($RutaObjeto[0]) {
            case 'api':
                switch ($RutaObjeto[1]) {
                    case 'pruebas':
                        switch ($Metodo) {
                            case 'GET':
                                $materia = $ParametrosGET['materia'] ?? null;
                                ObtenerPruebas($materia);
                                exit;
                            break;
                            case 'POST':
                                CrearArea($ParametrosPost);
                                exit;
                            break;
                            case 'PUT':
                                ActualizarArea($ParametrosPost);
                                exit;
                            break;
                            case 'DELETE':
                                EliminarArea($ParametrosPost);
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