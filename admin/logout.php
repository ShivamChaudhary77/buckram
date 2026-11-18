<?php
require __DIR__ . '/_init.php';
require_post();
admin_logout();
redirect_to(admin_url('login.php'));
