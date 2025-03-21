<?php
require_once __DIR__ . '/src/LoggerController.php';

$logger = new LoggerController();
$logger->cleanOldLogs(30);
