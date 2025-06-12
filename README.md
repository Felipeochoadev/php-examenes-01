# API REST - Sistema de Evaluación de Pruebas

Este proyecto implementa una API REST en PHP puro para gestionar exámenes, preguntas, estudiantes y sus calificaciones, utilizando SQLite como base de datos.

---

## 📁 Estructura de la Base de Datos

### Tabla: `areas`
- `id` (INTEGER, PRIMARY KEY)
- `materia` (TEXT, UNIQUE)
- `descripcion` (TEXT)

### Tabla: `pruebas`
- `id` (INTEGER, PRIMARY KEY)
- `id_area` (INTEGER, FK a `areas`)
- `titulo` (TEXT)
- `fecha` (DATE)

### Tabla: `preguntas`
- `id` (INTEGER, PRIMARY KEY)
- `id_prueba` (INTEGER, FK a `pruebas`)
- `enunciado` (TEXT)

### Tabla: `opciones`
- `id` (INTEGER, PRIMARY KEY)
- `id_pregunta` (INTEGER, FK a `preguntas`)
- `texto` (TEXT)
- `resultado` (INTEGER, 0 o 1)

### Tabla: `estudiantes`
- `id` (INTEGER, PRIMARY KEY)
- `identificacion` (INTEGER, UNIQUE)
- `nombre` (TEXT)

### Tabla: `resultados`
- `id` (INTEGER, PRIMARY KEY)
- `id_estudiante` (INTEGER, FK a `estudiantes`)
- `id_prueba` (INTEGER, FK a `pruebas`)
- `calificacion` (REAL)
- `fecha` (DATE)

---

## 🔧 Endpoints Disponibles

| Método | Endpoint                             | Descripción                               |
|--------|--------------------------------------|-------------------------------------------|
| GET    | `/api/areas`                         | Lista todas las áreas                     |
| GET    | `/api/pruebas?materia=matematicas`   | Obtener pruebas filtradas por materia     |
| POST   | `/api/areas`                         | Crea una nueva área                       |
| PUT    | `/api/areas?id={id}&materia={nombre}`| Actualiza un área (requiere ID y materia) |
| DELETE | `/api/areas?id={id}`                 | Elimina un área                           |



### 🔹 Obtener áreas disponibles

**GET** `/api/areas`

**Respuesta:**
```json
{
  "estado": "success",
  "mensaje": "Listado correctamente",
  "datos": [
    { "id": 1, "materia": "matematicas", "descripcion": "Matemáticas" },
    ...
  ]
}
```

---

### 🔹 Obtener pruebas por materia

**GET** `/api/pruebas?materia=matematicas`

**Respuesta:**
```json
{
  "estado": "success",
  "mensaje": "Listado correctamente",
  "datos": [
    {
      "id": 1,
      "id_area": 1,
      "titulo": "Prueba de Aritmética Básica",
      "fecha": "2025-06-01",
      ...
    },
    ...
  ]
}
```

---

### 🔹 Crear una nueva área

**POST** `/api/areas`

**Cuerpo del request (formato `application/x-www-form-urlencoded` o JSON):**
```json
{
  "materia": "historia",
  "descripcion": "Historia universal"
}
```

**Respuesta:**
```json
{
  "estado": "success",
  "mensaje": "Área registrada correctamente"
}
```

---

### 🔹 Actualizar un área

**PUT** `/api/areas?id=1&materia=historia`

**Parámetros (query):**
- `id` (int): ID del área a actualizar
- `materia` (string): Nuevo nombre de la materia

**Cuerpo del request (formato `application/x-www-form-urlencoded` o JSON):**
```json
{
  "descripcion": "Historia actualizada"
}
```

**Respuesta:**
```json
{
  "estado": "success",
  "mensaje": "Área actualizada correctamente"
}
```

---

### 🔹 Eliminar un área

**DELETE** `/api/areas?id=1`

**Parámetros (query):**
- `id` (int): ID del área a eliminar

**Respuesta:**
```json
{
  "estado": "success",
  "mensaje": "Área eliminada correctamente"
}
```


---

## ✅ Validación de Campos

La función `ValidarCampos()` se asegura de que los datos cumplan con las reglas de tipo, longitud y formato, por ejemplo:

```php
ValidarCampos($_POST, [
  'nombre' => ['tipo' => 'texto', 'requerido' => true, 'min' => 3],
  'edad'   => ['tipo' => 'entero', 'requerido' => true]
]);
```

---

## 🔒 Seguridad y Utilidades

- Detección de IP del cliente
- Normalización de texto (`áéíóúüñ`)
- Validación por tipo: texto, número, entero, fecha, booleano, email, etc.
- Estructura robusta de respuesta con estado HTTP y mensaje.

---

## ▶️ Inicialización

Para crear la base de datos y poblarla con datos base:

```php
InicializarBD();
```

Esto ejecuta `CrearBD()` si no existe la tabla `resultados`.

---

## 📌 Requisitos

- PHP 7.4 o superior
- SQLite

---

## 🚀 Autor

Creado por [Tu Nombre] - 2025
