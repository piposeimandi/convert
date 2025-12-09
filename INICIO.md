# 🎬 Comienza aquí

## ¿Primera vez? Sigue estos pasos:

### Paso 1️⃣: Verifica que todo está listo

```bash
cd /home/adrian/Documentos/TokyoGhoulRe-T01-03
./verificar.sh
```

Deberías ver:
```
✓ api.php
✓ index.html
✓ convert_cbr_to_epub.py
✓ convert_cbr_to_epub.php
✓ PHP 8.2.29
✓ Extensión ZipArchive
✓ 7z (7-Zip)
```

### Paso 2️⃣: Inicia el servidor web

```bash
php -S localhost:8111
```

Verás algo como:
```
[Tue Dec 09 17:30:00 2024] PHP 8.2.29 Development Server started
[Tue Dec 09 17:30:00 2024] Listening on http://localhost:8111
```

> ¿Prefieres Docker? Ejecuta `docker compose up -d` dentro del proyecto y accede igual a
> `http://localhost:8111`. Los archivos subidos quedan en tus carpetas locales.

### Paso 3️⃣: Abre tu navegador

Visita: **http://localhost:8111/index.html**

### Paso 4️⃣: ¡Usa la aplicación!

1. Arrastra un archivo `.cbr` a la zona destacada
2. Haz clic en "Convertir"
3. ¡Tu EPUB se descargará automáticamente! 🎉

---

## 🚀 Otros métodos

### Opción: Conversión automática (Línea de comandos)

**Con Python:**
```bash
python3 convert_cbr_to_epub.py
```

**Con PHP:**
```bash
php convert_cbr_to_epub.php
```

Esto convertirá todos los `.cbr` del directorio automáticamente.

### Opción: Script interactivo

```bash
./demo.sh
```

Menú interactivo con todas las opciones.

---

## 📚 Documentación

- **SETUP.md** - Instrucciones detalladas de configuración
- **README.md** - Documentación técnica de la API
- **RESUMEN.txt** - Resumen visual completo

---

## ❓ ¿Problemas?

### "Conexión rechazada"
Asegúrate de ejecutar:
```bash
php -S localhost:8111
```
En el directorio correcto.

### "7z not found"
Instala 7z:
```bash
sudo apt-get install p7zip-full  # Ubuntu/Debian
brew install p7zip               # macOS
```

### "No puedo abrir el archivo"
1. Verifica que es un `.cbr` real
2. Intenta otro archivo
3. Comprueba que no está corrupto

---

## 🎯 Características principales

✨ **Web moderna** - Interfaz hermosa con drag & drop  
⚡ **Rápida** - Conversión eficiente  
🔐 **Segura** - Validación de archivos  
📱 **Responsive** - Funciona en móvil  
🆓 **Gratis** - Sin dependencias de pago  

---

## 🔧 Stack técnico

- **Backend:** PHP 8.2
- **Frontend:** HTML5 + CSS3 + JavaScript
- **Herramientas:** 7z para extracción de RAR
- **Base de datos:** No necesaria (archivos temporales)

---

## ✅ Checklist rápido

- [ ] Ejecuté `verificar.sh` sin errores
- [ ] Ejecuté `php -S localhost:8111`
- [ ] Abrí `http://localhost:8111/index.html` en el navegador
- [ ] Arrastré un archivo `.cbr`
- [ ] Hice clic en "Convertir"
- [ ] Descargué el `.epub`

¡Si todo ✓, ¡estás listo! 🚀

---

## 💡 Consejos

1. **Primeros archivos:** Prueba con archivos pequeños primero
2. **Tamaño máximo:** La API permite hasta 500MB
3. **Navegadores:** Usa Chrome, Firefox, Safari o Edge
4. **Servidor de producción:** Para producción, usa Apache/Nginx

---

## 📞 Soporte rápido

| Problema | Solución |
|----------|----------|
| Puerto 8111 ocupado | Usa otro puerto: `php -S localhost:9000` |
| "7z not found" | Instala 7-Zip |
| Interfaz no carga | Verifica que PHP está corriendo |
| Archivo no se convierte | Intenta con otro `.cbr` |

---

## 🎓 Aprende más

Abre estas documentos después:
1. **SETUP.md** para configuración avanzada
2. **README.md** para documentación de API
3. **Código PHP** para entender la lógica interna

---

¡Que disfrutes convirtiendo! 📚✨

---

**Última actualización:** Diciembre 2024  
**Versión:** 1.0.0
