<?php
/**
 * Mobile Notes List — redirects to the canonical mobile notes page. The
 * desktop notes-list.php is a variant of notes.php; mobile already has a
 * unified notes view at ?page=notes.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
header('Location: ?page=notes');
exit;
