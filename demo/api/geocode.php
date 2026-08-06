<?php
// Proxy to the real geocode endpoint (no auth, no DB).
// Note the app/ segment: this pointed at ../../api/geocode.php, which does not
// exist, so demo address autocomplete fataled on every keystroke.
require __DIR__ . '/../../app/api/geocode.php';
