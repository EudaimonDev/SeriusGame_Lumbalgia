<?php

class AdaptiveEngine {
    const THRESHOLD_UP   = 0.80;
    const THRESHOLD_DOWN = 0.50;
    const WINDOW_SIZE    = 5;
    const TIME_PENALTY   = 15000;

    public static function getNextDifficulty(
        array  $recentAnswers,
        string $currentDifficulty
    ): string {
        if (count($recentAnswers) < self::WINDOW_SIZE) {
            return $currentDifficulty;
        }

        $window = array_slice($recentAnswers, -self::WINDOW_SIZE);

        $weightedScore = 0;
        foreach ($window as $answer) {
            $timeWeight     = ($answer['response_time_ms'] > self::TIME_PENALTY) ? 0.7 : 1.0;
            $weightedScore += ($answer['is_correct'] ? 1 : 0) * $timeWeight;
        }

        $rate   = $weightedScore / self::WINDOW_SIZE;
        $levels = ['easy', 'medium', 'hard'];
        $idx    = array_search($currentDifficulty, $levels, true);

        if ($rate >= self::THRESHOLD_UP   && $idx < 2) return $levels[$idx + 1];
        if ($rate <= self::THRESHOLD_DOWN && $idx > 0) return $levels[$idx - 1];

        return $currentDifficulty;
    }
}