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

$facultyId = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;

function fetchLive(int $electionId, string $type, int $facultyId): array {
    if ($facultyId > 0) {
        $rows = dbrows(
            'SELECT c.no, c.name, COUNT(v.id) AS votes
             FROM candidates c
             LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = c.election_id AND v.voter_faculty_id = ?
             WHERE c.election_id = ? AND c.type = ? AND c.is_active = 1
             GROUP BY c.id ORDER BY c.no ASC',
            [$facultyId, $electionId, $type]
        );
    } else {
        $rows = dbrows(
            'SELECT c.no, c.name, COUNT(v.id) AS votes
             FROM candidates c
             LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = c.election_id
             WHERE c.election_id = ? AND c.type = ? AND c.is_active = 1
             GROUP BY c.id ORDER BY c.no ASC',
            [$electionId, $type]
        );
    }
    $series = [];
    $labels = [];
    $meta   = [];
    foreach ($rows as $r) {
        $series[] = (int)$r['votes'];
        $labels[] = 'No. ' . $r['no'] . ' - ' . $r['name'];
        $meta[]   = ['no' => (int)$r['no'], 'name' => $r['name']];
    }
    return compact('series', 'labels', 'meta');
}

$presma = fetchLive($election['id'], 'presma', $facultyId);
$dpm    = fetchLive($election['id'], 'dpm', $facultyId);

echo json_encode([
    'series'     => $presma['series'],
    'labels'     => $presma['labels'],
    'meta'       => $presma['meta'],
    'total'      => array_sum($presma['series']),
    'series_dpm' => $dpm['series'],
    'labels_dpm' => $dpm['labels'],
    'meta_dpm'   => $dpm['meta'],
    'total_dpm'  => array_sum($dpm['series']),
    'ts'         => time(),
]);
