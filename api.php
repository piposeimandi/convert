<?php
ob_start();
session_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$actionLogged = $_POST['action'] ?? $_GET['action'] ?? 'sin-accion';
error_log('Acción recibida: ' . $actionLogged);
/**
 * CBR to EPUB Converter API
 * Endpoint para convertir archivos CBR a EPUB
 */


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class CBRtoEPUBAPI {
    private $uploadDir = './uploads';
    private $outputDir = './converted';
    private $tempDir;
    private $maxFileSize = 500 * 1024 * 1024; // 500MB
    private $historyLimit = 20;
    private array $commandCache = [];

    public function __construct() {
        $this->tempDir = sys_get_temp_dir();
        $this->createDirectories();
    }

    private function createDirectories() {
        @mkdir($this->uploadDir, 0755, true);
        @mkdir($this->outputDir, 0755, true);
    }

    private function response($success, $message, $data = null, $code = 200) {
        http_response_code($code);
        return json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];

            switch ($action) {
                case 'upload':
                    return $this->handleUpload();
                case 'convert':
                    return $this->handleConvert();
                case 'remove_history':
                    return $this->handleRemoveHistory();
                default:
                    echo $this->response(false, 'Acción no válida', null, 400);
            }
        } elseif ($method === 'GET') {
            $action = $_GET['action'] ?? null; // Usa null si no está definido
            if ($action) {
                switch ($action) {
                    case 'download':
                        return $this->handleDownload();
                    case 'history':
                        return $this->handleHistory();
                    default:
                        echo $this->response(false, 'Acción no válida', null, 400);
                }
            } else {
                echo $this->response(false, 'Parámetro "action" faltante', null, 400);
            }
        } else {
            echo $this->response(false, 'Solicitud inválida', null, 400);
        }
    }

    private function handleUpload() {
        if (!isset($_FILES['file'])) {
            echo $this->response(false, 'No se envió archivo', null, 400);
            return;
        }

        $file = $_FILES['file'];

        // Validaciones
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo $this->response(false, 'Error en la carga del archivo', null, 400);
            return;
        }

        if ($file['size'] > $this->maxFileSize) {
            echo $this->response(false, 'Archivo muy grande (máximo 500MB)', null, 413);
            return;
        }

        if (!preg_match('/\.cbr$/i', $file['name'])) {
            echo $this->response(false, 'Solo se aceptan archivos .cbr', null, 400);
            return;
        }

        $fileId = uniqid();
        $fileName = basename($file['name']);
        $filePath = $this->uploadDir . '/' . $fileId . '_' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            echo $this->response(false, 'Error al guardar el archivo', null, 500);
            return;
        }

        echo $this->response(true, 'Archivo cargado exitosamente', [
            'fileId' => $fileId,
            'fileName' => $fileName,
            'size' => $file['size']
        ]);
    }

    private function handleConvert() {
        if (!isset($_POST['fileId'])) {
            echo $this->response(false, 'ID de archivo no proporcionado', null, 400);
            return;
        }

        $fileId = preg_replace('/[^a-z0-9]/i', '', $_POST['fileId']);
        
        // Buscar archivo
        $files = glob($this->uploadDir . '/' . $fileId . '_*.cbr');
        
        if (empty($files)) {
            echo $this->response(false, 'Archivo no encontrado', null, 404);
            return;
        }

        $cbrFile = $files[0];
        $fileName = basename($cbrFile);
        $prettyBaseName = $this->stripExtension($this->sanitizeDisplayName($fileName));
        $epubName = $this->ensureUniqueFileName(
            ($prettyBaseName ?: pathinfo($fileName, PATHINFO_FILENAME)) . '.epub',
            $this->outputDir
        );
        $epubPath = $this->outputDir . '/' . $epubName;

        try {
            $bookTitle = $prettyBaseName ?: pathinfo($epubName, PATHINFO_FILENAME);
            $this->convertCBR($cbrFile, $epubPath, $bookTitle);

            if (!file_exists($epubPath)) {
                throw new Exception('El archivo EPUB no se creó');
            }

            // Limpiar archivo CBR
            @unlink($cbrFile);

            $epubSize = filesize($epubPath);
            $downloadUrl = 'api.php?action=download&file=' . urlencode($epubName);

            $this->appendHistory([
                'epubName' => $epubName,
                'displayName' => $epubName,
                'size' => $epubSize,
                'createdAt' => date('c'),
                'downloadUrl' => $downloadUrl
            ]);

            echo $this->response(true, 'Conversión exitosa', [
                'epubName' => $epubName,
                'size' => $epubSize,
                'downloadUrl' => $downloadUrl
            ]);
        } catch (Exception $e) {
            echo $this->response(false, 'Error en conversión: ' . $e->getMessage(), null, 500);
        }
    }

    private function convertCBR($cbrFile, $epubPath, $bookTitle) {
        $tempExtractDir = $this->tempDir . '/cbr_extract_' . uniqid();
        @mkdir($tempExtractDir, 0755, true);

        try {
            // Extraer CBR
            $this->extractArchive($cbrFile, $tempExtractDir);

            if (!is_dir($tempExtractDir) || count(scandir($tempExtractDir)) <= 2) {
                throw new Exception('No se pudo extraer el archivo CBR');
            }

            // Obtener imágenes
            $images = $this->getImages($tempExtractDir);

            if (empty($images)) {
                throw new Exception('No se encontraron imágenes en el archivo');
            }

            foreach ($images as $imgPath) {
                if (!is_file($imgPath) || filesize($imgPath) === 0) {
                    throw new Exception('Las imágenes extraídas están vacías. Instala "p7zip-full" y "p7zip-rar" para habilitar soporte CBR/RAR.');
                }
            }

            // Crear EPUB
            $this->createEPUB($images, $epubPath, $bookTitle);
        } finally {
            // Limpiar
            $this->removeDir($tempExtractDir);
        }
    }

    private function extractArchive($sourceFile, $destination) {
        [$success, $code, $output] = $this->executeShellCommand(
            $this->build7zCommand($sourceFile, $destination)
        );

        if ($success) {
            return;
        }

        $needsUnrar = $this->needsUnrarFallback($output);
        $hasUnrar = $this->commandExists('unrar');

        if ($hasUnrar) {
            [$unrarSuccess, $unrarCode, $unrarOutput] = $this->executeShellCommand(
                $this->buildUnrarCommand($sourceFile, $destination)
            );

            if ($unrarSuccess) {
                return;
            }

            $code = $unrarCode;
            $output = trim($output . "\n" . $unrarOutput);
        }

        $hint = ($needsUnrar && !$hasUnrar)
            ? 'Instala la utilidad "unrar" (RARLAB) o habilita soporte RAR5 para archivos CBR modernos.'
            : 'Verifica que el archivo CBR no esté corrupto o protegido con contraseña.';

        $details = $output ? "\n{$output}" : '';
        throw new Exception("Error al extraer el CBR (código {$code}). {$hint}{$details}");
    }

    private function executeShellCommand($command) {
        $output = [];
        exec($command, $output, $returnCode);
        $outputText = trim(implode("\n", $output));

        if ($returnCode !== 0) {
            error_log(sprintf('[CBRtoEPUB] Comando falló (%d): %s', $returnCode, $command));
            if ($outputText) {
                error_log($outputText);
            }
        }

        return [$returnCode === 0, $returnCode, $outputText];
    }

    private function needsUnrarFallback($output) {
        if (!$output) {
            return false;
        }

        $patterns = ['Unsupported Method', 'RAR version', 'encrypted', 'RAR5'];
        foreach ($patterns as $pattern) {
            if (stripos($output, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function commandExists($command) {
        if (array_key_exists($command, $this->commandCache)) {
            return $this->commandCache[$command];
        }

        $output = [];
        $returnCode = 0;
        @exec('command -v ' . escapeshellarg($command), $output, $returnCode);
        $exists = ($returnCode === 0);
        $this->commandCache[$command] = $exists;
        return $exists;
    }

    private function build7zCommand($sourceFile, $destination) {
        return sprintf(
            '7z x %s -o%s -aoa 2>&1',
            escapeshellarg($sourceFile),
            escapeshellarg($destination)
        );
    }

    private function buildUnrarCommand($sourceFile, $destination) {
        $targetDir = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return sprintf(
            'unrar x -o+ -y %s %s 2>&1',
            escapeshellarg($sourceFile),
            escapeshellarg($targetDir)
        );
    }

    private function getImages($folder) {
        $validExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
        $images = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $validExts)) {
                    $images[] = $file->getRealPath();
                }
            }
        }

        usort($images, function($a, $b) {
            $dirA = dirname($a);
            $dirB = dirname($b);
            if ($dirA !== $dirB) {
                return strcmp($dirA, $dirB);
            }
            $nameA = pathinfo($a, PATHINFO_FILENAME);
            $nameB = pathinfo($b, PATHINFO_FILENAME);
            return strnatcasecmp($nameA, $nameB);
        });

        return $images;
    }

    private function createEPUB($imageFiles, $outputEpub, $bookTitle) {
        $bookUUID = $this->generateUUID();
        $zip = new ZipArchive();

        if ($zip->open($outputEpub, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('No se pudo crear el archivo EPUB');
        }

        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

        $containerXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">' . "\n" .
            '    <rootfiles>' . "\n" .
            '        <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>' . "\n" .
            '    </rootfiles>' . "\n" .
            '</container>';
        $zip->addFromString('META-INF/container.xml', $containerXml);

            $pageRefs = [];
            $imageRefs = [];
            $spineRefs = [];
            $pageEntries = [];

        foreach ($imageFiles as $i => $imgPath) {
            $imgName = basename($imgPath);
            $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
            $mediaType = $ext === 'jpg' ? 'image/jpeg' : "image/{$ext}";

                $imgId = sprintf('img_%04d', $i);
                $imageRefs[] = sprintf(
                    '        <item id="%s" href="images/%s" media-type="%s"/>',
                    htmlspecialchars($imgId),
                htmlspecialchars($imgName),
                htmlspecialchars($mediaType)
            );

                $pageId = sprintf('page_%04d', $i);
                $pageName = sprintf('page_%04d.xhtml', $i);
                $pageRefs[] = sprintf(
                    '        <item id="%s" href="pages/%s" media-type="application/xhtml+xml"/>',
                    htmlspecialchars($pageId),
                    htmlspecialchars($pageName)
                );
                $spineRefs[] = sprintf('        <itemref idref="%s"/>', htmlspecialchars($pageId));
                $pageEntries[] = [
                    'id' => $pageId,
                    'name' => $pageName,
                    'label' => $i + 1
                ];

                $pageContent = <<<HTML
    <?xml version="1.0" encoding="UTF-8"?>
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Página %d</title>
        <meta charset="UTF-8"/>
        <style type="text/css">
            body { margin: 0; padding: 0; background: #000; }
            img { display: block; width: 100%%; height: auto; }
        </style>
    </head>
    <body>
        <img src="../images/%s" alt="Página %d"/>
    </body>
    </html>
    HTML;
                $safeImgName = htmlspecialchars($imgName, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $pageMarkup = sprintf($pageContent, $i + 1, $safeImgName, $i + 1);
                $zip->addFromString("OEBPS/pages/{$pageName}", $pageMarkup);
        }

            // OEBPS/content.opf
            $manifestItems = implode("\n", array_merge($pageRefs, $imageRefs));
        $spineItems = implode("\n", $spineRefs);
        $date = date('Y-m-d');

        $contentOpf = <<<EOX
<?xml version="1.0" encoding="UTF-8"?>
<package version="2.0" xmlns="http://www.idpf.org/2007/opf" unique-identifier="uuid_id">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>{$bookTitle}</dc:title>
        <dc:creator>Unknown</dc:creator>
        <dc:date>{$date}</dc:date>
        <dc:identifier id="uuid_id">uuid:{$bookUUID}</dc:identifier>
        <dc:language>es</dc:language>
    </metadata>
    <manifest>
        <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
{$manifestItems}
    </manifest>
    <spine toc="ncx">
{$spineItems}
    </spine>
</package>
EOX;
        $zip->addFromString('OEBPS/content.opf', $contentOpf);

        $navPoints = [];
        foreach ($pageEntries as $entry) {
            $navPoints[] = sprintf(
                '        <navPoint id="%s" playOrder="%d"><navLabel><text>Página %d</text></navLabel><content src="pages/%s"/></navPoint>',
                htmlspecialchars($entry['id']),
                (int)$entry['label'],
                (int)$entry['label'],
                htmlspecialchars($entry['name'])
            );
        }

        $navMap = implode("\n", $navPoints);
        $tocNcx = <<<EOX
<?xml version="1.0" encoding="UTF-8"?>
<ncx version="2005-1" xmlns="http://www.daisy.org/z3986/2005/ncx/">
    <head>
        <meta name="dtb:uid" content="uuid:{$bookUUID}"/>
    </head>
    <docTitle><text>{$bookTitle}</text></docTitle>
    <navMap>
{$navMap}
    </navMap>
</ncx>
EOX;
        $zip->addFromString('OEBPS/toc.ncx', $tocNcx);

        foreach ($imageFiles as $imgPath) {
            $imgName = basename($imgPath);
            if (!is_readable($imgPath)) {
                throw new Exception('No se puede leer la imagen: ' . $imgName);
            }

            if (!$zip->addFile($imgPath, "OEBPS/images/{$imgName}")) {
                throw new Exception('No se pudo agregar la imagen al EPUB: ' . $imgName);
            }
        }

        $zip->close();
    }

    private function getHistory() {
        if (!isset($_SESSION['history']) || !is_array($_SESSION['history'])) {
            $_SESSION['history'] = [];
        }
        return $_SESSION['history'];
    }

    private function saveHistory(array $history) {
        $_SESSION['history'] = $history;
    }

    private function appendHistory(array $entry) {
        $history = $this->getHistory();
        array_unshift($history, $entry);
        if (count($history) > $this->historyLimit) {
            $history = array_slice($history, 0, $this->historyLimit);
        }
        $this->saveHistory($history);
    }

    private function sanitizeDisplayName($fileName) {
        return preg_replace('/^[a-f0-9]+_/', '', $fileName);
    }

    private function stripExtension($fileName) {
        return preg_replace('/\.[^.]+$/', '', $fileName);
    }

    private function ensureUniqueFileName($fileName, $directory) {
        $pathInfo = pathinfo($fileName);
        $base = $pathInfo['filename'] ?? 'archivo';
        $ext = isset($pathInfo['extension']) && $pathInfo['extension'] !== ''
            ? '.' . $pathInfo['extension']
            : '';

        $candidate = $base . $ext;
        $counter = 1;

        while (file_exists(rtrim($directory, '/') . '/' . $candidate)) {
            $candidate = sprintf('%s (%d)%s', $base, $counter, $ext);
            $counter++;
        }

        return $candidate;
    }

    private function isInHistory($fileName) {
        foreach ($this->getHistory() as $entry) {
            if (($entry['epubName'] ?? null) === $fileName) {
                return true;
            }
        }
        return false;
    }

    private function removeHistoryEntryByEpub($epubName) {
        $history = $this->getHistory();
        $new = array_values(array_filter($history, function($entry) use ($epubName) {
            return (($entry['epubName'] ?? null) !== $epubName) && (($entry['downloadUrl'] ?? null) !== $epubName);
        }));
        $this->saveHistory($new);
        return count($history) !== count($new);
    }

    private function handleRemoveHistory() {
        $epub = $_POST['epubName'] ?? null;
        if (!$epub) {
            echo $this->response(false, 'Nombre de EPUB faltante', null, 400);
            return;
        }

        $epub = basename($epub);
        $removed = $this->removeHistoryEntryByEpub($epub);
        if ($removed) {
            echo $this->response(true, 'Entrada eliminada del historial', null);
        } else {
            echo $this->response(false, 'No se encontró la entrada en el historial', null, 404);
        }
    }

    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function removeDir($dir) {
        if (!is_dir($dir)) return false;
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDir($path);
                } else {
                    @unlink($path);
                }
            }
        }
        return @rmdir($dir);
    }

    private function handleDownload() {
        if (!isset($_GET['file'])) {
            echo $this->response(false, 'Archivo no especificado', null, 400);
            return;
        }

        $fileName = basename($_GET['file']);
        $filePath = $this->outputDir . '/' . $fileName;

        if (!file_exists($filePath) || !preg_match('/\.epub$/i', $fileName)) {
            echo $this->response(false, 'Archivo no encontrado', null, 404);
            return;
        }

        // Nota: ya no exigimos que el archivo esté en el historial de sesión
        // para permitir la descarga. Eliminar una entrada del historial solo
        // afecta la interfaz del usuario; el archivo físico se mantiene.

        header('Content-Type: application/epub+zip');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }

    private function handleHistory() {
        echo $this->response(true, 'Historial obtenido', $this->getHistory());
    }

    private function handleStatus() {
        $files = scandir($this->uploadDir);
        $uploadedFiles = array_filter($files, function($f) {
            return $f !== '.' && $f !== '..' && preg_match('/\.cbr$/i', $f);
        });

        echo $this->response(true, 'Estado obtenido', [
            'uploadedFiles' => count($uploadedFiles),
            'totalUploaded' => array_sum(array_map(function($f) {
                return filesize($this->uploadDir . '/' . $f);
            }, $uploadedFiles))
        ]);
    }

    private function handleList() {
        $files = scandir($this->uploadDir);
        $uploadedFiles = array_filter($files, function($f) {
            return $f !== '.' && $f !== '..' && preg_match('/\.cbr$/i', $f);
        });

        $list = [];
        foreach ($uploadedFiles as $f) {
            $path = $this->uploadDir . '/' . $f;
            $list[] = [
                'name' => preg_replace('/^[a-z0-9]+_/', '', $f),
                'size' => filesize($path),
                'fileId' => preg_replace('/_.+/', '', $f)
            ];
        }

        echo $this->response(true, 'Lista de archivos', $list);
    }
}

$api = new CBRtoEPUBAPI();
$api->handleRequest();
?>
