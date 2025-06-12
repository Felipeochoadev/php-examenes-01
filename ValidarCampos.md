# Documentación de la función ValidarCampos

La función `ValidarCampos` se encarga de validar un array de campos con base en un conjunto de reglas definidas por el desarrollador. Esta función está diseñada para ser reutilizable y robusta, permitiendo validaciones comunes como:

* Campos requeridos
* Tipos de datos
* Longitudes mínimas y máximas
* Formato de fechas y horas
* Valores permitidos (enum)
* Validaciones por expresiones regulares

## Firma de la función

```php
ValidarCampos(array $campos, array $reglas)
```

### Parámetros:

* `$campos`: Array asociativo con los datos recibidos (por ejemplo, desde JSON o `$_POST`).
* `$reglas`: Array asociativo con las reglas de validación por cada campo.

## Estructura de las reglas

Cada entrada del array `$reglas` debe tener esta estructura:

```php
$reglas = [
    'campo1' => [
        'requerido' => true,
        'tipo' => 'texto',
        'min' => 3,
        'max' => 100,
        'regex' => '/^[a-zA-Z ]+$/',
        'enum' => ['opcion1', 'opcion2']
    ],
    'campo2' => [
        'tipo' => 'email',
        'requerido' => true
    ],
    'campo3' => [
        'tipo' => 'date'
    ]
];
```

## Tipos de validación admitidos

### 1. `requerido`

Indica si el campo es obligatorio.

```php
'requerido' => true
```

### 2. `tipo`

Valida el tipo de dato esperado:

* `texto` – Cadena de caracteres
* `numerico` – Número (entero o decimal)
* `entero` – Solo enteros
* `float` – Solo flotantes
* `email` – Formato de correo electrónico válido
* `booleano` – true, false, 0, 1, "0", "1"
* `date` – Fecha en formato `YYYY-MM-DD`
* `time` – Hora en formato `HH:MM:SS`
* `datetime` – Fecha y hora en formato `YYYY-MM-DD HH:MM:SS`

### 3. `min`

Mínimo de caracteres permitidos (aplica para texto).

```php
'min' => 3
```

### 4. `max`

Máximo de caracteres permitidos (aplica para texto).

```php
'max' => 100
```

### 5. `regex`

Permite validar el formato del valor usando una expresión regular (regex). Las expresiones regulares son patrones que describen un conjunto de cadenas. Son útiles para validar formatos personalizados como:

* Códigos alfanuméricos
* Placas de vehículos
* Cédulas o identificaciones
* Contraseñas con ciertos requisitos

Ejemplo: Para permitir únicamente letras, números y guiones bajos:

```php
'regex' => '/^[a-zA-Z0-9_]+$/'
```

Este patrón significa:

* `^` y `$`: delimitan el inicio y el final de la cadena (para que no se permita texto adicional antes o después).
* `[a-zA-Z0-9_]`: permite letras (mayúsculas y minúsculas), números y guión bajo.
* `+`: uno o más caracteres válidos.
* `/^[a-zA-Z]+$/`: Solo letras (mayúsculas y minúsculas) Útil para: nombres, apellidos..
* `/^[a-zA-Z\s]+$/`: Letras y espacios Útil para: nombres completos.
* `/^\d+$/`: Números solamente Útil para: edades, IDs numéricos, cantidades.
* `/^\d+(\.\d{1,2})?$/`: Números decimales Útil para: precios, montos con decimales (hasta 2 cifras).
* `/^[\w\.-]+@[\w\.-]+\.\w+$/`: Correo electrónico (básico) Útil para: validar emails sin usar filter_var().
* `/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/`: Contraseñas con mínimo una mayúscula, una minúscula, un número y 8 caracteres Útil para: políticas de seguridad en contraseñas.
* `/^[A-Z]{3}\d{3}$/`: Placa de automovil colombiana Ejemplo válido: ABC123.
* `/^[A-Z]{3}\d{2}[A-Z]{1}$/`: Placa de moto colombiana Ejemplo válido: ABC123A.
* `/^\d{6,10}$/`: Número de cédula colombiano (sin puntos ni espacios) Útil para: evitar caracteres no deseados.
* `/^(https?:\/\/)?[\w\-]+(\.[\w\-]+)+[/#?]?.*$/`: URL Útil para: enlaces web válidos.
* `/^\d{4}-\d{2}-\d{2}$/`: Fecha en formato YYYY-MM-DD Útil para: asegurar el formato antes de hacer strtotime() o validación con DateTime.

Puedes personalizar el patrón según tus necesidades.

### 6. `enum`

Define un conjunto de valores permitidos para el campo.

```php
'enum' => ['admin', 'user', 'guest']
```

## Respuesta en caso de error

En caso de que alguna validación falle, la función detiene la ejecución mediante `exit` y llama a:

```php
EnviarRespuesta(400, 'error', 'Validación fallida', $errores);
```

Donde `$errores` es un array asociativo con los campos y sus respectivos mensajes de error.

## Ejemplo de uso

```php
$datos = json_decode(file_get_contents('php://input'), true);
$reglas = [
    'nombre' => [
        'requerido' => true,
        'tipo' => 'texto',
        'min' => 3
    ],
    'edad' => [
        'requerido' => true,
        'tipo' => 'entero'
    ],
    'correo' => [
        'tipo' => 'email'
    ],
    'fecha_nacimiento' => [
        'tipo' => 'date'
    ]
];

ValidarCampos($datos, $reglas);
```

---

Esta función te permite centralizar y estandarizar la validación de entradas, mejorando la mantenibilidad y seguridad de tus APIs en PHP puro.

### Validación de Campos No Permitidos

Al procesar solicitudes HTTP (por ejemplo, en una API REST), es fundamental **asegurar que solo se reciban los campos esperados**. Esto previene:

- Errores por datos inesperados o mal formateados.
- Inyecciones o sobreescritura accidental de datos sensibles.
- Uso incorrecto del API por parte de clientes o usuarios.

### 🧩 ¿Cómo funciona?

Antes de validar el contenido de cada campo, comparamos los datos recibidos con la lista de campos esperados definidos en `$reglas`. Si hay claves que no están definidas explícitamente, la solicitud se rechaza.

```php
$camposNoPermitidos = array_diff_key($campos, array_flip(array_keys($reglas)));

if (!empty($camposNoPermitidos)) {
    error_log("index.php ::::: ERROR LINEA(".__LINE__.") ::::: Campos no permitidos ::::: ".json_encode($camposNoPermitidos));
    EnviarRespuesta(400, 'error', 'Campos no permitidos', $camposNoPermitidos);
    exit;
}