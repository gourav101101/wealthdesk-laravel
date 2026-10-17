<?php

// Package Laravel source only; exclude local keys, records, uploads and dependencies.
$root = dirname(__DIR__);
$archive = dirname($root).DIRECTORY_SEPARATOR.'wealthdesk-laravel.zip';
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Enable PHP's zip extension to create the source archive.\n");
    exit(1);
}
$zip = new ZipArchive;
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create source archive.');
$count = 0;
$prefix = 'wealthdesk-laravel/';
foreach (['artisan', 'index.php', '.htaccess', '.user.ini', 'composer.json', 'composer.lock', '.env.example', '.env.hostinger.example', 'phpunit.xml', 'README.md', '.editorconfig', '.gitattributes', '.gitignore'] as $name) {
    $zip->addFile($root.DIRECTORY_SEPARATOR.$name, $prefix.$name);
    $count++;
}
foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'tests', 'scripts', 'docs'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.DIRECTORY_SEPARATOR.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (preg_match('~\.(sqlite|sqlite-wal|sqlite-shm)$~', $relative)) continue;
        if (str_starts_with($relative, 'bootstrap/cache/') && basename($relative) !== '.gitignore') continue;
        if (str_starts_with($relative, 'public/storage/') || str_starts_with($relative, 'public/hot')) continue;
        $zip->addFile($file->getPathname(), $prefix.$relative);
        $count++;
    }
}
foreach (['storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/testing', 'storage/framework/views', 'storage/logs'] as $directory) {
    $zip->addEmptyDir($prefix.$directory);
    $zip->addFromString($prefix.$directory.'/.gitignore', "*\n!.gitignore\n");
}
$zip->close();
echo "Created {$archive} ({$count} source files; no local runtime data).\n";
