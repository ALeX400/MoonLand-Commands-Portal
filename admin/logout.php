<?php

declare(strict_types=1);

session_start();

$config = include __DIR__ . '/../config/commands-config.php';
$adminConfig = $config['admin'] ?? [];
$sessionKey = $adminConfig['session_key'] ?? 'moonland_commands_admin';
$csrfKey = $sessionKey . '_csrf';

unset($_SESSION[$sessionKey], $_SESSION[$csrfKey]);

session_regenerate_id(true);

header('Location: index.php');
exit;
