<?php
// Redirect to the main habits page (All Habits grid is now the default)
// This file is kept for backwards compatibility with existing links/bookmarks
header('Location: ?page=habits');
exit;
