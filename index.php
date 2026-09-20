<?php
declare(strict_types=1);

/**
 * Repository entry point.
 *
 * The V1 implementation now lives in v1/. Visitors reaching the project root
 * are sent to the current V2 interface.
 */
header('Location: v2/index.php', true, 302);
exit;
