<?php
// Recursively scan and fix PHP files under pages/ to remove BOM, leading output, and closing PHP tag
$root = __DIR__ . '/../pages';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$fixed = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') continue;
    $content = file_get_contents($path);
    $orig = $content;
    // remove UTF-8 BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    // remove any whitespace/newlines before <?php
    $content = preg_replace('/^\s*(<\?php)/i', '$1', $content, 1);
    // remove closing tag at end and trailing whitespace
    $content = preg_replace('/\?>\s*$/', '', $content);
    // normalize line endings to \n
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    if ($content !== $orig) {
        file_put_contents($path, $content);
        echo "Fixed: $path\n";
        $fixed++;
    }
}
if ($fixed === 0) echo "No files changed.\n";
else echo "Total fixed: $fixed\n";
