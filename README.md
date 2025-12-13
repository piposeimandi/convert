# CBR to EPUB Converter — Frontend + API

![Captura de la UI](assets/screenshot1.png)

Aplicación ligera que incluye una **API en PHP** y un **frontend web (single-file `index.html`)** para convertir archivos CBR a EPUB compatibles con lectores electrónicos.

Este repositorio contiene tanto el backend (`api.php`) como la interfaz de usuario, por lo que puedes usarlo como API desde otras aplicaciones o ejecutar la interfaz para uso interactivo.

## 🚀 Características principales

- Frontend: drag & drop, barra de progreso y historial de conversiones en el navegador.
- API REST: endpoints para subir, convertir, descargar y listar archivos.
- Extracción robusta: usa `7z` y, como fallback, `unrar` para CBR/RAR5. También soporta archivos CBZ/ZIP.
- Historial cliente/servidor: las conversiones se guardan en la sesión y también en `localStorage` del navegador.
- Eliminación segura: al eliminar una entrada desde la UI el archivo físico NO se borra; se renombra agregando la etiqueta `.DELETE`.

## 📋 Requisitos

- PHP 8+ con `ZipArchive` habilitado.
- `p7zip-full` (7z) y `unrar` recomendados para máximo soporte.
- Docker y Docker Compose (opcional).

## 🔧 Cómo ejecutar

### Opción A — Con Docker (recomendado para reproducibilidad)

```bash
# Construir la imagen (primera vez)
docker compose build

# Levantar en segundo plano (expone el servicio en el puerto 8111)
docker compose up -d

# Ver logs
docker compose logs -f

# Parar
docker compose down
```

La imagen incluye `7z`, `unrar` y la extensión `zip` para PHP. El servicio escucha en el puerto `8111` por defecto dentro del contenedor.

### Opción B — PHP integrado (para pruebas rápidas)

```bash
php -d upload_max_filesize=600M -d post_max_size=600M -d memory_limit=1G -S localhost:8111
```

Luego abre `http://localhost:8111/` en tu navegador.

### Requisitos de carpetas

El servidor usa `uploads/` para subidas temporales y `converted/` para EPUBs generados. Asegúrate de que existan y sean escribibles:

```bash
mkdir -p uploads converted
chmod 755 uploads converted
```

## 📖 Uso

### Interfaz web

1. Abre `index.html` en el navegador (o accede al host/puerto donde esté corriendo el servicio).
2. Arrastra o selecciona un archivo `.cbr`.
3. Pulsa `Convertir`.
4. Cuando termine, el frontend mostrará un mensaje: "Archivo convertido — ahora puedes descargarlo desde el historial" y preparará la UI para aceptar otro archivo.
5. Descarga el EPUB desde el panel `Historial`.

Nota: la descarga ya no se inicia automáticamente; el historial contiene entradas combinadas entre la sesión del servidor y el `localStorage` del navegador.

### Endpoints principales (API REST)

- `POST api.php` — `action=upload` + `file=@...` → sube un CBR.
- `POST api.php` — `action=convert&fileId=...` → convierte un CBR cargado a EPUB.
- `GET api.php?action=download&file=<epubName>` → descarga el EPUB si existe en `converted/`.
- `GET api.php?action=history` → devuelve el historial de la sesión (JSON).
- `POST api.php` — `action=remove_history&epubName=...` → elimina la entrada del historial y marca el archivo con `.DELETE` (no lo borra físicamente).

Ejemplo de subida con curl:

```bash
curl -F "action=upload" -F "file=@comic.cbr" http://localhost:8111/api.php
```

Ejemplo de conversión:

```bash
curl -X POST -d "action=convert&fileId=<FILE_ID>" http://localhost:8111/api.php
```

## 📂 Historial y eliminación

- El historial que ves en el frontend es una mezcla entre la sesión del servidor y el `localStorage` del navegador; se sincronizan al obtener el historial.
- Cuando el usuario elimina una entrada desde la UI, la API intentará renombrar el archivo en `converted/` añadiendo `.DELETE` antes de la extensión (p. ej. `manga.epub` → `manga.DELETE.epub`).
- La entrada se elimina de la sesión y del `localStorage` local; el archivo físico queda marcado y disponible para descarga si conoces su nombre.

Esta estrategia evita borrados accidentales y permite auditoría/recuperación manual.

## ⚙️ Configuración

- Ajusta el límite máximo de subida en `api.php`:

```php
private $maxFileSize = 500 * 1024 * 1024; // 500MB
```

- El proyecto incluye `docker/php-upload.ini` con límites ampliados (`600M`, `1G` de memoria, etc.). Si no usas Docker, puedes usar `.user.ini` o pasar los flags a `php` al iniciar.

## 🆘 Solución de problemas (rápida)

- Si `7z` devuelve "Unsupported Method", instala `unrar` para dar soporte a RAR5.
- Si falta `ZipArchive`, instala `libzip` y habilita la extensión `zip` en PHP.
- Revisa `/tmp/php-error.log` dentro del contenedor si ves problemas con los headers o conversiones.

## 🔒 Seguridad y limitaciones

- El API valida extensión y tamaño; aun así, sirve con precaución en entornos públicos.
- No hay autenticación de usuario por defecto — la historia se guarda por sesión PHP.

## 📦 Estructura del proyecto

```
. 
├── index.html           # Frontend UI (single-file)
├── api.php              # Backend API (PHP)
├── uploads/             # Archivos CBR subidos (temporal)
├── converted/           # Archivos EPUB generados
├── docker/              # Archivos de configuración de Docker
├── Dockerfile
├── docker-compose.yml
└── README.md
```

## 📝 Licencia

Uso libre. Modifica y distribuye como desees.

---

**Última actualización**: Diciembre 2025
