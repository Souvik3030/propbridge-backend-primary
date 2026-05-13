<?php
$header = "Bearer "; // Maybe empty token?
echo "Bearer: " . base64_encode(hash('sha256', $header, true)) . "\n";

$header = ""; // Empty header?
echo "Empty: " . base64_encode(hash('sha256', $header, true)) . "\n";

$header = "Bearer null";
echo "Bearer null: " . base64_encode(hash('sha256', $header, true)) . "\n";
