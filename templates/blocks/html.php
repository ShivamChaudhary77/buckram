<?php
/**
 * Free HTML section: keeps one-off layouts exactly as they were.
 * The HTML is cleaned when saved from the dashboard (allow-list sanitizer).
 * @var array $b  html
 */
echo rtrim((string) ($b['html'] ?? '')), "\n\n";
