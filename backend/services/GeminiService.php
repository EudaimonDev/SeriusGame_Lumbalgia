<?php

class GeminiService {

    private string $geminiKey;
    private string $groqKey;

    private string $geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    private string $groqEndpoint   = 'https://api.groq.com/openai/v1/chat/completions';
    private string $groqModel      = 'llama-3.3-70b-versatile';

    public function __construct() {
        $this->geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $this->groqKey   = $_ENV['GROQ_API_KEY']   ?? '';
    }

    public function generateQuestions(
        string $category,
        string $difficulty,
        int    $count = 5,
        string $language = 'español'
    ): array {
        // Intenta primero con Gemini
        try {
            return $this->callGemini($category, $difficulty, $count, $language);
        } catch (\Exception $e) {
            // Si Gemini falla por cualquier razón, usa Groq como fallback
            error_log("Gemini falló, usando Groq como fallback. Razón: " . $e->getMessage());
            return $this->callGroq($category, $difficulty, $count, $language);
        }
    }

    // ── Gemini ──────────────────────────────────────────────

    private function callGemini(
        string $category,
        string $difficulty,
        int    $count,
        string $language
    ): array {
        $prompt = $this->buildPrompt($category, $difficulty, $count, $language);

        $body = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 2048,
            ]
        ]);

        $ch = curl_init("{$this->geminiEndpoint}?key={$this->geminiKey}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Gemini HTTP $httpCode");
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return $this->parseJson($text);
    }

    // ── Groq (fallback) ──────────────────────────────────────

    private function callGroq(
        string $category,
        string $difficulty,
        int    $count,
        string $language
    ): array {
        $prompt = $this->buildPrompt($category, $difficulty, $count, $language);

        $body = json_encode([
            'model'       => $this->groqModel,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Eres un experto en salud ocupacional y ergonomía. Respondes únicamente con JSON válido, sin markdown ni bloques de código.'
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens'  => 2048,
        ]);

        $ch = curl_init($this->groqEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqKey,
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new \Exception(
                'Groq HTTP ' . $httpCode . ' — ' .
                ($error['error']['message'] ?? $response)
            );
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        return $this->parseJson($content);
    }

    // ── Helpers compartidos ──────────────────────────────────

    private function buildPrompt(
        string $category,
        string $difficulty,
        int    $count,
        string $language
    ): string {
        $difficultyLabel = match($difficulty) {
            'easy'   => 'básico (conocimiento general, terminología simple)',
            'medium' => 'intermedio (comprensión aplicada, relaciones causa-efecto)',
            'hard'   => 'avanzado (análisis clínico, prevención específica)',
            default  => 'básico'
        };

        return "Genera exactamente {$count} preguntas de opción múltiple sobre '{$category}'
con nivel de dificultad {$difficultyLabel}, orientadas a estudiantes universitarios
de software. Todo el texto debe estar en {$language}.

Responde ÚNICAMENTE con un array JSON válido, sin markdown, sin bloques de código:
[
  {
    \"question_text\": \"texto de la pregunta en {$language}\",
    \"option_a\": \"opción\",
    \"option_b\": \"opción\",
    \"option_c\": \"opción\",
    \"option_d\": \"opción\",
    \"correct_answer\": \"a\",
    \"feedback_text\": \"explicación clínica en {$language}\"
  }
]

Reglas:
- correct_answer debe ser exactamente 'a', 'b', 'c' o 'd' en minúscula
- Todo el texto en {$language}
- Sin markdown ni bloques de código en tu respuesta";
    }

    private function parseJson(string $text): array {
        // Limpiar posibles bloques markdown
        $text = preg_replace('/```json|```/i', '', $text);
        $text = trim($text);

        $questions = json_decode($text, true);

        if (!is_array($questions)) {
            throw new \Exception('La respuesta no es un JSON válido: ' . substr($text, 0, 200));
        }

        return $questions;
    }
}