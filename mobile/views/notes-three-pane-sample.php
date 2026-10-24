<?php
/**
 * Mobile Notes Three-Pane Sample — the desktop file is explicitly marked
 * SAMPLE/STANDALONE (not integrated). On mobile we simply forward to the live
 * notes view so deep links don't 404.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
header('Location: ?page=notes');
exit;
