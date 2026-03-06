<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/auth.php';

if (!admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$election = active_election();
if (!$election) {
    echo json_encode(['series' => [], 'labels' => [], 'meta' => []]);
    exit;
}

$rows = dbrows(
    'SELECT c.no, c.name, COUNT(v.id) AS votes
     FROM candidates c
     LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = c.election_id
     WHERE c.election_id = ? AND c.type = ? AND c.is_active = 1
     GROUP BY c.id ORDER BY c.no ASC',
    [$election['id'], 'presma']
);

$series = [];
$labels = [];
$meta   = [];
foreach ($rows as $r) {
    $series[] = (int)$r['votes'];
    $labels[] = 'No. ' . $r['no'] . ' - ' . $r['name'];
    $meta[]   = ['no' => (int)$r['no'], 'name' => $r['name']];
}

echo json_encode([
    'series' => $series,
    'labels' => $labels,
    'meta'   => $meta,
    'total'  => array_sum($series),
    'ts'     => time(),
]);
