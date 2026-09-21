<?php
/**
 * student/roadmap.php
 * Backward compatibility redirect to unified Learning Path (Weekly Plan tab)
 * NEXTBEYOND V2 — Learning Navigation Refactor
 */
declare(strict_types=1);

$params = $_GET;
$params['tab'] = 'weekly';
if (isset($params['id']) && !isset($params['roadmap_id'])) {
    $params['roadmap_id'] = (int)$params['id'];
}

$target = 'learning-path.php?' . http_build_query($params);
header('Location: ' . $target, true, 302);
exit;
