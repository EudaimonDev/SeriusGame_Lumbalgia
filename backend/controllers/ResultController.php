<?php

require_once __DIR__ . '/../models/ResultModel.php';
require_once __DIR__ . '/../models/SessionModel.php';

class ResultController {

    private ResultModel  $resultModel;
    private SessionModel $sessionModel;

    public function __construct() {
        $this->resultModel  = new ResultModel();
        $this->sessionModel = new SessionModel();
    }

    /**
     * GET /api/results/me
     * Historial de resultados del estudiante autenticado
     */
    public function myResults(Request $request, array $payload): void {
        $userId  = $payload['sub'];
        $results = $this->resultModel->getByUser($userId);

        // Calcular knowledge gain si hay pretest y posttest
        $pretest  = null;
        $posttest = null;

        foreach ($results as $r) {
            if ($r['test_type'] === 'pretest'  && !$pretest)  $pretest  = $r;
            if ($r['test_type'] === 'posttest' && !$posttest) $posttest = $r;
        }

        $gain = null;
        if ($pretest && $posttest) {
            $gain = round($posttest['score'] - $pretest['score'], 2);
        }

        Response::success([
            'results'        => $results,
            'knowledge_gain' => $gain,
            'pretest_score'  => $pretest  ? $pretest['score']  : null,
            'posttest_score' => $posttest ? $posttest['score'] : null,
        ]);
    }

    /**
     * GET /api/results/group
     * Estadísticas comparativas por grupo (solo admin)
     * Para el experimento cuasi-experimental pre/post-test
     */
    public function groupReport(Request $request, array $payload): void {
        $stats = $this->resultModel->getGroupStats();

        // Reestructurar para fácil consumo en el frontend
        $report = [
            'control'      => ['pretest' => null, 'posttest' => null, 'gain' => null],
            'experimental' => ['pretest' => null, 'posttest' => null, 'gain' => null],
        ];

        foreach ($stats as $row) {
            $group    = $row['group_type'];
            $testType = $row['test_type'];

            if (isset($report[$group])) {
                $report[$group][$testType] = [
                    'avg_score' => round((float)$row['avg_score'], 2),
                    'total'     => (int)$row['total'],
                ];
            }
        }

        // Calcular ganancia por grupo
        foreach (['control', 'experimental'] as $group) {
            $pre  = $report[$group]['pretest']['avg_score']  ?? null;
            $post = $report[$group]['posttest']['avg_score'] ?? null;

            if ($pre !== null && $post !== null) {
                $report[$group]['gain'] = round($post - $pre, 2);
            }
        }

        Response::success($report);
    }
}