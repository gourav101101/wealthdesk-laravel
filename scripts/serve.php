<?php

// Local PHP server using Laravel's own router, with limits matching the upload forms.
$root = dirname(__DIR__);
$process = proc_open([
    PHP_BINARY, '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=20M',
    '-S', '127.0.0.1:8000', $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root.'/public');
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start the local PHP server.\n");
    exit(1);
}
exit(proc_close($process));
