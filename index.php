<?php
// Every address that isn't a real file comes here (see .htaccess / deploy/nginx.conf).
require __DIR__ . '/app/bootstrap.php';
front_emit(front_dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/'));
