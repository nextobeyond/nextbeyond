<?php
declare(strict_types=1);

// Keep the previous Admin URL working while the generator lives in ai-exam-app.
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ai-exam-app/' . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
