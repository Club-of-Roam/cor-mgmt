<?php
// Deployment replacement for uploads/ticker_images/process.php on existing sites.
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['error' => 'This upload endpoint has been retired. Use the authenticated WordPress media endpoint.']);
