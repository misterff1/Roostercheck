<?php

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit('403 Forbidden');
}

return [
    'school' => 'schoolname',
    'token' => 'yourtoken',
    'location' => 'yourlocation',
    'pin_length' => 6,                  // minimum 3, maximum 6
    'pin_reset_timeout' => 15,          // minimum 5, maximum 60 (seconds)
    'logout_timeout' => 20,             // minimum 5, maximum 60 (seconds)
];
