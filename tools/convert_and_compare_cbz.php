<?php
// tools/convert_and_compare_cbz.php
// Usage: php tools/convert_and_compare_cbz.php "/ruta/al/archivo.cbz"

if ($argc < 2) {
    echo "Uso: php tools/convert_and_compare_cbz.php /ruta/al/archivo.cbz\n";
    exit(1);
}

$source = $argv[1];
if (!is_file($source)) {
    echo "Archivo no encontrado: $source\n";
    exit(2);
}

$validExts = ['jpg','jpeg','png','gif','bmp','webp','tiff'];

$applyFilter = in_array('--filter', $argv, true);

$tempDir = sys_get_temp_dir() . '/cbz_' . uniqid();
@mkdir($tempDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($source) !== true) {
    echo "No se pudo abrir el CBZ\n";
    exit(3);
}

$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    if (!$stat) continue;
    $name = $stat['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, $validExts)) {
        $entries[] = ['index' => $i, 'name' => $name, 'ext' => $ext];
    }
}

if (empty($entries)) {
    echo "No se encontraron imágenes en el CBZ\n";
    exit(4);
}

$// Extract entries in zip order to temp dir
$extracted = [];
foreach ($entries as $k => $e) {
    // If requested, skip macOS resource forks and Thumbs.db like the API
    if ($applyFilter) {
        $lowname = strtolower($e['name']);
        if (strpos(basename($lowname), '._') === 0) continue;
        if (strpos($lowname, '__macosx') !== false) continue;
        if (basename($lowname) === 'thumbs.db') continue;
    }

    $stream = $zip->getStream($e['name']);
    if ($stream === false) continue;
    $outName = sprintf('%04d', $k) . '.' . $e['ext'];
    $outPath = $tempDir . '/' . $outName;
    $out = fopen($outPath, 'w');
    while (!feof($stream)) {
        fwrite($out, fread($stream, 8192));
    }
    fclose($out);
    fclose($stream);
    $extracted[] = ['orig_name' => $e['name'], 'path' => $outPath, 'ext' => $e['ext']];
}
$zip->close();

// Prepare output EPUB path
$base = pathinfo($source, PATHINFO_FILENAME);
$sanitized = preg_replace('/[^A-Za-z0-9 _\-\.]/', '_', $base);
$outputDir = __DIR__ . '/../converted';
@mkdir($outputDir, 0755, true);
$outEpub = $outputDir . '/' . $sanitized . '.epub';

// Create EPUB similar to api.php logic (unique img names)
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$bookTitle = $sanitized;
$bookUUID = generateUUID();
$zipOut = new ZipArchive();
if ($zipOut->open($outEpub, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "No se pudo crear EPUB $outEpub\n";
    exit(5);
}
$zipOut->addFromString('mimetype', 'application/epub+zip');
$zipOut->setCompressionName('mimetype', ZipArchive::CM_STORE);
$containerXml = '<?xml version="1.0" encoding="UTF-8"?>\n' .
    '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">\n' .
    '    <rootfiles>\n' .
    '        <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>\n' .
    '    </rootfiles>\n' .
    '</container>';
$zipOut->addFromString('META-INF/container.xml', $containerXml);

$pageRefs = [];
$imageRefs = [];
$spineRefs = [];
$pageEntries = [];

foreach ($extracted as $i => $img) {
    $ext = $img['ext'];
    $mediaType = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : "image/{$ext}";
    $imgId = sprintf('img_%04d', $i);
    $imgOutName = $imgId . '.' . $ext;
    $imageRefs[] = sprintf('        <item id="%s" href="images/%s" media-type="%s"/>', htmlspecialchars($imgId), htmlspecialchars($imgOutName), htmlspecialchars($mediaType));
    $pageId = sprintf('page_%04d', $i);
    $pageName = sprintf('page_%04d.xhtml', $i);
    $pageRefs[] = sprintf('        <item id="%s" href="pages/%s" media-type="application/xhtml+xml"/>', htmlspecialchars($pageId), htmlspecialchars($pageName));
    $spineRefs[] = sprintf('        <itemref idref="%s"/>', htmlspecialchars($pageId));
    $pageEntries[] = ['id' => $pageId, 'name' => $pageName, 'label' => $i + 1];
    $pageContent = <<<HTML
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Página %d</title>
    <meta charset="UTF-8"/>
    <style type="text/css">body { margin:0; padding:0; background:#000 } img { display:block; width:100%%; height:auto; }</style>
</head>
<body>
    <img src="../images/%s" alt="Página %d"/>
</body>
</html>
HTML;
    $safeImgName = htmlspecialchars($imgOutName, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $pageMarkup = sprintf($pageContent, $i + 1, $safeImgName, $i + 1);
    $zipOut->addFromString("OEBPS/pages/{$pageName}", $pageMarkup);
}

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
$zipOut->addFromString('OEBPS/content.opf', $contentOpf);
$navPoints = [];
foreach ($pageEntries as $entry) {
    $navPoints[] = sprintf('        <navPoint id="%s" playOrder="%d"><navLabel><text>Página %d</text></navLabel><content src="pages/%s"/></navPoint>', htmlspecialchars($entry['id']), (int)$entry['label'], (int)$entry['label'], htmlspecialchars($entry['name']));
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
$zipOut->addFromString('OEBPS/toc.ncx', $tocNcx);

// Add image files
foreach ($extracted as $i => $img) {
    $ext = $img['ext'];
    $imgOutName = sprintf('img_%04d', $i) . '.' . $ext;
    if (!is_readable($img['path'])) {
        echo "No readable image: {$img['path']}\n";
        $zipOut->close();
        exit(6);
    }
    $zipOut->addFile($img['path'], "OEBPS/images/{$imgOutName}");
}
$zipOut->close();

// Compute hashes and report
$origCount = count($entries);
$epubCount = count($extracted);
$hashes = [];
foreach ($extracted as $img) {
    $hashes[] = md5_file($img['path']);
}
$uniqueHashes = array_unique($hashes);

echo "CBZ original entries: {$origCount}\n";
echo "Imágenes extraídas: {$epubCount}\n";
echo "Imágenes únicas por hash: " . count($uniqueHashes) . "\n";
echo "EPUB creado: {$outEpub}\n\n";

// Show first 10 original names vs first 10 extracted filenames
echo "Primeras 10 entradas del CBZ (orden interno):\n";
for ($i=0;$i<min(10, count($entries));$i++) {
    echo " - " . $entries[$i]['name'] . "\n";
}

echo "\nPrimeras 10 imágenes dentro del EPUB (nombres internos):\n";
for ($i=0;$i<min(10, count($extracted));$i++) {
    echo sprintf(" - %s (hash=%s)\n", basename($extracted[$i]['path']), md5_file($extracted[$i]['path']));
}

// Cleanup temp extracted files
// (keep for inspection) -- do not delete automatically

exit(0);
