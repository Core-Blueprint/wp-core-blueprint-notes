<?php
// Run only via wp eval-file --skip-wordpress. No WordPress or SQL operations.
// WP-CLI passes positional parameters as the local $args variable.
if (!isset($args) || !is_array($args) || $args !== ['p4-argv-probe']) {
    throw new RuntimeException('Notes P4 WP-CLI positional arguments unavailable.');
}
echo "Notes P4 WP-CLI eval-file arguments: PASS\n";
