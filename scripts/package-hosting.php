<?php

// Usage: php -d extension=zip scripts/package-hosting.php /path/to/production/vendor
// Install vendor in a clean staging directory with composer install --no-dev first.
$root = dirname(__DIR__);
$vendor = realpath($argv[1] ?? '');
if (! $vendor || ! is_file($vendor.'/autoload.php') || ! is_file($vendor.'/composer/installed.json')) {
    throw new RuntimeException('Pass a production vendor directory generated with composer install --no-dev.');
}
$installed = json_decode(file_get_contents($vendor.'/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
if (($installed['dev'] ?? true) !== false) {
    throw new RuntimeException('The vendor directory contains development dependencies. Run composer install --no-dev in staging.');
}
require __DIR__.'/package.php';
$source = new ZipArchive;
$source->open(dirname($root).'/wealthdesk-laravel.zip');
$target = new ZipArchive;
$output = dirname($root).'/wealthdesk-hostinger.zip';
$target->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE);
for ($i = 0; $i < $source->numFiles; $i++) {
    $name = substr($source->getNameIndex($i), strlen('wealthdesk-laravel/'));
    if (str_ends_with($name, '/')) {
        $target->addEmptyDir(rtrim($name, '/'));
    } else {
        $target->addFromString($name, $source->getFromIndex($i));
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendor, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && ! $file->isLink()) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($vendor) + 1));
        $target->addFile($file->getPathname(), 'vendor/'.$relative);
    }
}
$target->close();
$source->close();
echo "Created {$output}: root-folder Apache layout, production vendor, no local environment, database or uploads.\n";
