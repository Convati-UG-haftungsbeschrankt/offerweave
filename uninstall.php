<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}
if (!class_exists(\OfferWeave\Uninstall::class)) {
    require_once __DIR__ . '/includes/Uninstall.php';
}
\OfferWeave\Uninstall::run();
