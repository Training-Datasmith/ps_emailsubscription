<?php

error_reporting(-1);
ini_set('display_errors', '0');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    fwrite(STDERR, "PROBE_ERROR\n");

    exit(255);
});
