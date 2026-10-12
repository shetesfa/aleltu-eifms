<?php
/**
 * Aleltu Farm EIFMS - Aleltu POS System integration point (SAD section 16.4).
 *
 * This system is outbound-only toward the POS: it tells the POS what was
 * delivered and when. It never reads sales, pricing, or customer data back
 * — that boundary is enforced by only ever calling dispatch_push_to_pos()
 * in includes/functions.php, nowhere else in the codebase.
 *
 * Leave POS_WEBHOOK_URL empty until Aleltu's POS team confirms a real
 * endpoint — the app runs fine without it; delivery confirmations just
 * won't be pushed anywhere yet.
 */
if (!defined('POS_WEBHOOK_URL')) {
    define('POS_WEBHOOK_URL', '');
}
if (!defined('POS_WEBHOOK_SECRET')) {
    define('POS_WEBHOOK_SECRET', '');
}
