# CBR to EPUB Converter - API Web

Una aplicación web moderna para convertir cómics en formato CBR a libros electrónicos EPUB compatibles con Kindle.

## 🚀 Características

- **Interfaz moderna**: Drag & drop intuitivo para cargar archivos
- **Conversión rápida**: Extrae imágenes de CBR y crea EPUBs válidos
- **Descarga automática**: El archivo se descarga cuando termina la conversión
- **API REST**: Endpoints para integración con otras aplicaciones
- **Indicador de progreso**: Barra de progreso en tiempo real
- **Responsive**: Funciona en desktop y dispositivos móviles
- **Sin dependencias**: Solo usa herramientas estándar (PHP, 7z)

## 📋 Requisitos

- PHP 7.2+
- Extensión `ZipArchive` (generalmente incluida)
- Herramientas `7z` y `unrar` (para archivos CBR con compresión RAR5)
- Servidor web (Apache, Nginx, etc.) o Docker + Docker Compose (opcional)

## 🔧 Instalación

### Opción 1: Servidor Web (Recomendado)

1. Coloca los archivos en tu documentroot del servidor web:
   - `index.html` - Frontend
   - `api.php` - Backend API
   - Carpetas: `uploads/` y `converted/` (creadas automáticamente)

2. Asegúrate que las carpetas tengan permisos de escritura:
   ```bash
   chmod 755 uploads converted
   ```

3. Accede a `http://localhost/ruta-a-tu-app/index.html`

### Opción 2: PHP Built-in Server (Para pruebas)

```bash
# Navega al directorio del proyecto
cd /ruta/al/proyecto

# Inicia el servidor
php -S localhost:8111

# Accede a http://localhost:8111
```

### Opción 3: Docker Compose (Entorno aislado)

```bash
# Construir la imagen (solo la primera vez)
docker compose build

# Levantar el servicio y dejarlo escuchando en 8111
docker compose up -d

# Ver logs si lo necesitas
docker compose logs -f

# Detener los contenedores
docker compose down
```

Los volúmenes definidos en `docker-compose.yml` mantienen sincronizadas las carpetas
`uploads/` y `converted/` con tu disco local, así que los archivos subidos o convertidos
seguirán disponibles aunque detengas los contenedores.

## 📖 Uso

### Interfaz Web

1. Abre `index.html` en tu navegador
2. Arrastra un archivo `.cbr` al área de drop o haz clic para seleccionar
3. Haz clic en "Convertir"
4. El archivo EPUB se descargará automáticamente cuando esté listo

### API REST

#### 1. Subir archivo

**Endpoint**: `POST /api.php`

```bash
curl -F "action=upload" -F "file=@comic.cbr" http://localhost:8111/api.php
```

**Respuesta exitosa**:
```json
{
  "success": true,
  "message": "Archivo cargado exitosamente",
  "data": {
    "fileId": "507f1f77bcf86cd799439011",
    "fileName": "comic.cbr",
    "size": 85000000
  }
}
```

#### 2. Convertir archivo

**Endpoint**: `POST /api.php`

```bash
curl -X POST -d "action=convert&fileId=507f1f77bcf86cd799439011" http://localhost:8111/api.php
```

**Respuesta exitosa**:
```json
{
  "success": true,
  "message": "Conversión exitosa",
  "data": {
    "epubName": "comic.epub",
    "size": 45000000,
    "downloadUrl": "api.php?action=download&file=comic.epub"
  }
}
```

#### 3. Descargar archivo

**Endpoint**: `GET /api.php?action=download&file=comic.epub`

Descarga el archivo EPUB directamente.

#### 4. Estado del servidor

**Endpoint**: `GET /api.php?action=status`

```bash
curl http://localhost:8111/api.php?action=status
```

**Respuesta**:
```json
{
  "success": true,
  "message": "Estado obtenido",
  "data": {
    "uploadedFiles": 2,
    "totalUploaded": 170000000
  }
}
```

#### 5. Listar archivos

**Endpoint**: `GET /api.php?action=list`

```bash
curl http://localhost:8111/api.php?action=list
```

**Respuesta**:
```json
{
  "success": true,
  "message": "Lista de archivos",
  "data": [
    {
      "name": "comic1.cbr",
      "size": 85000000,
      "fileId": "507f1f77bcf86cd799439011"
    }
  ]
}
```

## 📁 Estructura de carpetas

```
.
├── index.html           # Frontend web
├── api.php              # Backend API
├── uploads/             # Archivos CBR subidos (temporal)
├── converted/           # Archivos EPUB generados
├── convert_cbr_to_epub.py   # Convertidor Python (alternativo)
├── convert_cbr_to_epub.php  # Convertidor PHP CLI (alternativo)
├── Dockerfile               # Imagen PHP + 7-Zip
└── docker-compose.yml       # Stack listo para `docker compose up`
```

## ⚙️ Configuración

### Límite de tamaño de archivo

En `api.php`, línea ~15:
```php
private $maxFileSize = 500 * 1024 * 1024; // 500MB
```

Cambia este valor según tus necesidades.

### Ajustes de PHP (.user.ini / Docker)

El proyecto incluye un archivo `.user.ini` en la raíz **y** un override específico para Docker (`docker/php-upload.ini`) que elevan los límites de PHP para admitir cargas grandes:

- `upload_max_filesize = 600M`
- `post_max_size = 600M`
- `memory_limit = 1024M`
- `max_execution_time = 600`

Si sirves la app con el servidor integrado (`php -S ...`) o con PHP-FPM/FastCGI, estos ajustes se aplican automáticamente. En el contenedor Docker, el archivo se copia a `/usr/local/etc/php/conf.d/uploads.ini`, así que no tienes que hacer nada adicional. En entornos donde `.user.ini` no se respeta (por ejemplo, algunos hosts con configuración propia), copia los mismos valores a tu `php.ini` o pásalos al iniciar el servidor, por ejemplo:

```bash
php -d upload_max_filesize=600M -d post_max_size=600M -d memory_limit=1G -S localhost:8111
```

Los errores quedarán registrados en `/tmp/php-error.log` (ver `.user.ini`).

### Directorio de salida

Los archivos convertidos se guardan en la carpeta `converted/`. Puedes cambiar esto en `api.php`:
```php
private $outputDir = './converted';
```

## 🔒 Seguridad

- Solo acepta archivos `.cbr`
- Valida tamaño máximo de archivo
- Limpia archivos temporales automáticamente
- Descargas de archivos usan nombres sanitizados
- Protección contra inyección de rutas

## 🆘 Solución de problemas

### Error: "7z: command not found"
Instala 7-Zip:
```bash
# Ubuntu/Debian
sudo apt-get install p7zip-full

# macOS
brew install p7zip

# Windows
# Descarga desde: https://www.7-zip.org/
```

### Error: "Unsupported Method" al convertir

Los CBR recientes suelen usar RAR5, que no está completamente soportado por `p7zip`. Instala `unrar` (o `unar`) para proporcionar el descompresor propietario:

```bash
# Ubuntu/Debian
sudo apt-get install unrar

# macOS (homebrew)
brew install unar
```

En Docker no necesitas hacer nada: la imagen ya incluye `unrar` y la API lo usa como respaldo si `7z` falla.

### Error: "No se encontraron imágenes"
El archivo CBR podría estar corrupto o no contener imágenes. Intenta:
1. Verifica el archivo con `7z l archivo.cbr`
2. Intenta el archivo en otro lector CBR

### Las carpetas `uploads/` y `converted/` no existen
Créalas manualmente:
```bash
mkdir -p uploads converted
chmod 755 uploads converted
```

### El servidor devuelve 500 Internal Server Error
Verifica:
1. PHP y ZipArchive están instalados: `php -i | grep -i zip`
2. Las carpetas tienen permisos de escritura: `ls -la`
3. Los logs del servidor: `/var/log/apache2/error.log` (Apache)

## 📊 Límites recomendados

- **Tamaño máximo**: 500MB
- **Tiempo máximo de conversión**: Depende del servidor (típicamente 2-5 min)
- **Imágenes máximas por EPUB**: No hay límite técnico

## 🎯 Ejemplos de uso

### Con JavaScript Fetch

```javascript
// Subir archivo
const formData = new FormData();
formData.append('action', 'upload');
formData.append('file', fileInput.files[0]);

const uploadResponse = await fetch('api.php', {
    method: 'POST',
    body: formData
});

const uploadData = await uploadResponse.json();
const fileId = uploadData.data.fileId;

// Convertir
const convertResponse = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=convert&fileId=${fileId}`
});

const convertData = await convertResponse.json();
console.log(convertData.data.downloadUrl);
```

### Con Python

```python
import requests

# Subir
files = {'file': open('comic.cbr', 'rb')}
data = {'action': 'upload'}
response = requests.post('http://localhost:8111/api.php', 
                        data=data, files=files)
file_id = response.json()['data']['fileId']

# Convertir
data = {'action': 'convert', 'fileId': file_id}
response = requests.post('http://localhost:8111/api.php', data=data)
print(response.json())
```

## 📝 Licencia

Uso libre. Modifica y distribuye como desees.

## 🤝 Contribuir

¿Encontraste un bug o tienes una sugerencia? ¡Repórtalo!

## 📞 Soporte

Si necesitas ayuda:
1. Verifica que 7z está instalado
2. Revisa los permisos de las carpetas
3. Comprueba que PHP está configurado correctamente
4. Consulta los logs del servidor

---

**Versión**: 1.0.0  
**Última actualización**: Diciembre 2024
