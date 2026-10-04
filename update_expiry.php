<?php
/**
 * Backwards-compatible entry point.
 *
 * The actual expiry/reminder logic is now centralized in
 * expiry_reminders.php so that the old scheduler does not bypass
 * SMS reminders.
 */

require_once __DIR__ . '/expiry_reminders.php';
