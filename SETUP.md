# 🚀 Guía de Inicio Rápido

## Instalación en 5 minutos

### 1️⃣ Requisitos previos

```bash
# Verificar PHP
php -v

# Verificar 7z (si no lo tienes, instálalo)
7z --version

# Verificar unrar (para CBR con RAR5)
unrar --version

# Si no lo tienes instalado:
# Ubuntu/Debian
sudo apt-get install p7zip-full
sudo apt-get install unrar

# macOS
brew install p7zip
brew install unar

# Windows: Descarga desde https://www.7-zip.org/
```

### 2️⃣ Preparar el proyecto

Todos los archivos ya están en el directorio:
```
.
├── api.php                      # Backend API
├── index.html                   # Frontend web
├── convert_cbr_to_epub.py      # Convertidor Python (opcional)
├── convert_cbr_to_epub.php     # Convertidor PHP (opcional)
├── demo.sh                      # Script interactivo
├── README.md                    # Documentación completa
└── [uploads/]                  # Se crea automáticamente
└── [converted/]                # Se crea automáticamente
```

### 3️⃣ Opción A: Servidor Web (Recomendado)

**Para Apache/Nginx:**

1. Copia los archivos a tu documentroot:
   ```bash
   cp -r * /var/www/html/cbr-converter/
   cd /var/www/html/cbr-converter
   chmod 755 uploads converted
   ```

2. Accede a: `http://localhost/cbr-converter/index.html`

### 3️⃣ Opción B: PHP Built-in Server (Desarrollo)

**El método más simple:**

```bash
# En el directorio del proyecto
cd /ruta/al/proyecto

# Iniciar servidor (puerto 8111)
php -S localhost:8111

# Abrir navegador
open http://localhost:8111/index.html
# o
http://localhost:8111/index.html
```

> ℹ️ **Límites elevados**: El archivo `.user.ini` del proyecto ya configura `upload_max_filesize` y `post_max_size` en 600MB. Si tu entorno ignora `.user.ini`, ejecuta `php -d upload_max_filesize=600M -d post_max_size=600M -d memory_limit=1G -S localhost:8111` para evitar el error `POST Content-Length ... exceeds the limit` con cómics grandes.

### 3️⃣ Opción C: Script Demo (Interactivo)
#️⃣ Opción D: Docker Compose (Entorno listo para producción ligera)

```bash
# Construir imagen
docker compose build

# Levantar servicio en segundo plano
docker compose up -d

# Ver logs o apagar
docker compose logs -f
docker compose down
```

Esto expone la app en `http://localhost:8111` y mantiene `uploads/` y `converted/`
sincronizados con tu máquina mediante volúmenes.


```bash
# Hacer ejecutable
chmod +x demo.sh

# Ejecutar
./demo.sh

# Selecciona opción 1 para iniciar servidor web
```

## 📖 Uso

### Interfaz Web

1. Abre `index.html` en tu navegador
2. Arrastra un archivo `.cbr` (o haz clic para seleccionar)
3. Haz clic en "Convertir"
4. El EPUB se descarga automáticamente

### Línea de comandos (CLI)

**Opción 1: Python**
```bash
python3 convert_cbr_to_epub.py
```

**Opción 2: PHP**
```bash
php convert_cbr_to_epub.php
```

## 🧪 Probar la API

### Subir archivo
```bash
curl -F "action=upload" -F "file=@miarchivo.cbr" http://localhost:8111/api.php
```

### Convertir
```bash
curl -d "action=convert&fileId=TU_FILE_ID" http://localhost:8111/api.php
```

### Descargar
```bash
curl http://localhost:8111/api.php?action=download&file=miarchivo.epub -o output.epub
```

## ❓ Solución de problemas

### "Address already in use" (Puerto ocupado)

Usar otro puerto:
```bash
php -S localhost:9000
```

### "7z: command not found"

Instalar 7z:
```bash
# Ubuntu/Debian
sudo apt-get install p7zip-full

# Verificar
7z --version
```

### Error de permisos en carpetas

```bash
cd /ruta/al/proyecto
chmod 755 uploads converted
```

### ZipArchive no disponible

Verificar:
```bash
php -i | grep -i zip
```

Si no está habilitado, en `php.ini` busca y descomenta:
```ini
extension=zip
```

## 📊 Límites

- **Tamaño máximo**: 500MB (configurable en `api.php` línea 15)
- **Tipos aceptados**: .cbr
- **Navegadores soportados**: Todos los modernos (Chrome, Firefox, Safari, Edge)
- **Tiempo de conversión**: 2-5 minutos (depende del tamaño)

## 🎯 Próximos pasos

1. **Interfaz web**: Abre `index.html` para la experiencia visual
2. **API avanzada**: Consulta `README.md` para endpoint details
3. **Servidor**: Para producción, usa Apache/Nginx en lugar del servidor integrado

## 💡 Tips

- Los archivos se limpian automáticamente después de descargar
- Puedes convertir múltiples archivos secuencialmente
- El navegador mantiene el historial de descargas

## 📞 Ayuda

Si necesitas ayuda:
1. Verifica que 7z está instalado: `7z --version`
2. Comprueba los permisos: `ls -la`
3. Lee `README.md` para documentación completa

---

**¿Listo para convertir?** 📚✨
