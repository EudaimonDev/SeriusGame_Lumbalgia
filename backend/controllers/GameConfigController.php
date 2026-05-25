<?php

require_once __DIR__ . '/../models/GameConfigModel.php';

class GameConfigController {

    private GameConfigModel $configModel;

    public function __construct() {
        $this->configModel = new GameConfigModel();
    }

    /**
     * GET /api/config
     * Pública — el juego lee la configuración
     */
    public function get(Request $request, array $payload = []): void {
        Response::success($this->configModel->get());
    }

    /**
     * PUT /api/admin/config
     * Solo admin — actualiza la configuración
     */
    public function update(Request $request, array $payload): void {
        $lives          = (int) $request->input('lives', 3);
        $questions      = (int) $request->input('questions', 16);
        $timeSeconds    = (int) $request->input('time_seconds', 15);
        $pointsCorrect  = (int) $request->input('points_correct', 10);
        $pointsBonus    = (int) $request->input('points_bonus', 5);

        if ($lives < 1 || $lives > 10) {
            Response::error('Las vidas deben estar entre 1 y 10');
        }
        if ($questions < 5 || $questions > 50) {
            Response::error('Las preguntas deben estar entre 5 y 50');
        }
        if ($timeSeconds < 5 || $timeSeconds > 120) {
            Response::error('El tiempo debe estar entre 5 y 120 segundos');
        }

        $this->configModel->update([
            'lives'          => $lives,
            'questions'      => $questions,
            'time_seconds'   => $timeSeconds,
            'points_correct' => $pointsCorrect,
            'points_bonus'   => $pointsBonus,
        ]);

        Response::success($this->configModel->get(), 'Configuración actualizada');
    }
}