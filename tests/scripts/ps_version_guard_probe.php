<?php

require __DIR__ . '/probe_error_handler.php';

echo "GUARD_BEFORE\n";
require dirname(__DIR__, 2) . '/ps_emailsubscription.php';
echo "GUARD_AFTER\n";
