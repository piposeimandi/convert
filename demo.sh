#!/bin/bash
# Script de demostración de la API CBR to EPUB

echo "═════════════════════════════════════════"
echo "  CBR to EPUB Converter - Demo API"
echo "═════════════════════════════════════════"
echo ""

# Cambiar al directorio del proyecto
cd "$(dirname "$0")"

# Color codes
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para imprimir títulos
print_header() {
    echo -e "${BLUE}▶ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# 1. Verificar requisitos
print_header "1. Verificando requisitos..."

if ! command -v php &> /dev/null; then
    echo "❌ PHP no está instalado"
    exit 1
fi
print_success "PHP instalado: $(php -v | head -1)"

if ! command -v 7z &> /dev/null; then
    echo "❌ 7z no está instalado. Instálalo con: sudo apt-get install p7zip-full"
    exit 1
fi
print_success "7z instalado"

# 2. Verificar directorios
print_header "2. Verificando directorios..."

if [ ! -d "uploads" ]; then
    mkdir -p uploads
    print_success "Directorio 'uploads' creado"
else
    print_success "Directorio 'uploads' existe"
fi

if [ ! -d "converted" ]; then
    mkdir -p converted
    print_success "Directorio 'converted' creado"
else
    print_success "Directorio 'converted' existe"
fi

# 3. Verificar archivos
print_header "3. Verificando archivos necesarios..."

for file in api.php index.html; do
    if [ -f "$file" ]; then
        print_success "Archivo $file encontrado"
    else
        echo "❌ Archivo $file no encontrado"
        exit 1
    fi
done

# 4. Verificar sintaxis PHP
print_header "4. Verificando sintaxis PHP..."

php -l api.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    print_success "Sintaxis PHP correcta"
else
    echo "❌ Error de sintaxis en api.php"
    php -l api.php
    exit 1
fi

# 5. Buscar archivos CBR
print_header "5. Buscando archivos CBR..."

cbr_count=$(ls -1 *.cbr 2>/dev/null | wc -l)
if [ $cbr_count -gt 0 ]; then
    print_success "Se encontraron $cbr_count archivo(s) CBR"
    ls -1 *.cbr | sed 's/^/  • /'
else
    print_warning "No se encontraron archivos CBR en este directorio"
fi

# 6. Información del servidor
print_header "6. Información del servidor"
echo "  📁 Directorio actual: $(pwd)"
echo "  🔧 Versión PHP: $(php -v | head -1 | cut -d' ' -f1,2)"

# Verificar ZipArchive
php -r "echo extension_loaded('zip') ? '  ✓ Extensión ZipArchive: Habilitada' : '  ✗ Extensión ZipArchive: Deshabilitada';" && echo ""

# 7. Opciones
echo ""
print_header "¿Qué deseas hacer?"
echo ""
echo "1) Iniciar servidor web (puerto 8111)"
echo "2) Convertir archivos CBR (CLI - Python)"
echo "3) Convertir archivos CBR (CLI - PHP)"
echo "4) Limpiar archivos temporales"
echo "5) Ver estado de la API"
echo "6) Salir"
echo ""

read -p "Selecciona una opción (1-6): " option

case $option in
    1)
        print_header "Iniciando servidor PHP..."
        echo ""
        echo -e "${GREEN}Servidor iniciado en http://localhost:8111${NC}"
        echo -e "${GREEN}Abre tu navegador y accede a http://localhost:8111/index.html${NC}"
        echo ""
        echo "Presiona Ctrl+C para detener el servidor"
        echo ""
        # Inicia el servidor PHP integrado
        php -S localhost:8111 &
        ;;
    2)
        if [ -f "convert_cbr_to_epub.py" ]; then
            print_header "Convertidor CBR a EPUB (Python)"
            python3 convert_cbr_to_epub.py
        else
            echo "❌ Script Python no encontrado"
        fi
        ;;
    3)
        if [ -f "convert_cbr_to_epub.php" ]; then
            print_header "Convertidor CBR a EPUB (PHP)"
            php convert_cbr_to_epub.php
        else
            echo "❌ Script PHP no encontrado"
        fi
        ;;
    4)
        print_header "Limpiando archivos temporales..."
        rm -f uploads/*
        rm -f converted/*
        print_success "Archivos temporales eliminados"
        ;;
    5)
        print_header "Información de la API"
        echo ""
        echo "Endpoints disponibles:"
        echo "  • POST /api.php - action=upload     (Subir archivo)"
        echo "  • POST /api.php - action=convert    (Convertir archivo)"
        echo "  • GET /api.php - action=download    (Descargar archivo)"
        echo "  • GET /api.php - action=status      (Estado del servidor)"
        echo "  • GET /api.php - action=list        (Listar archivos)"
        echo ""
        echo "Documentación: Ver README.md"
        ;;
    6)
        echo "¡Hasta luego!"
        exit 0
        ;;
    *)
        echo "❌ Opción inválida"
        exit 1
        ;;
esac

echo ""
print_success "Operación completada"
