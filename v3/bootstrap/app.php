<?php
declare(strict_types=1);

defined('V2_ROOT') || define('V2_ROOT', dirname(__DIR__, 2) . '/v2');
defined('V3_ROOT') || define('V3_ROOT', dirname(__DIR__));
defined('GYMPRO_BACKEND_ROOT') || define('GYMPRO_BACKEND_ROOT', V2_ROOT);
defined('GYMPRO_UI_ROOT') || define('GYMPRO_UI_ROOT', V3_ROOT);
defined('GYMPRO_FRONTEND') || define('GYMPRO_FRONTEND', 'v3');

require_once V2_ROOT . '/bootstrap/app.php';
