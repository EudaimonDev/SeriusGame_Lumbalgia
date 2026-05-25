<?php

class GroqService {

    private string $apiKey;
    private string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    private string $model    = 'llama-3.3-70b-versatile';

    public function __construct() {
        $this->apiKey = $_ENV['GROQ_API_KEY'] ?? '';
    }

    public function generateQuestions(string $category, string $difficulty, int $count = 5): array {
        $difficultyLabel = match($difficulty) {
            'easy'   => 'básico (conocimiento general, terminología simple)',
            'medium' => 'intermedio (comprensión aplicada, relaciones causa-efecto)',
            'hard'   => 'avanzado (análisis clínico, prevención específica)',
            default  => 'básico'
        };

        $prompt = "Genera exactamente {$count} preguntas de opción múltiple sobre '{$category}' con nivel de dificultad {$difficultyLabel}, orientadas a estudiantes universitarios de software que pasan largas horas frente al computador.

Responde ÚNICAMENTE con un array JSON válido, sin texto adicional, sin markdown, sin bloques de código. Exactamente este formato:
[
  {
    \"question_text\": \"texto de la pregunta\",
    \"option_a\": \"primera opción\",
    \"option_b\": \"segunda opción\",
    \"option_c\": \"tercera opción\",
    \"option_d\": \"cuarta opción\",
    \"correct_answer\": \"a\",
    \"feedback_text\": \"explicación clínica breve de por qué esa es la respuesta correcta\"
  }
]

Reglas estrictas:
- correct_answer debe ser exactamente 'a', 'b', 'c' o 'd' en minúscula
- feedback_text debe ser explicación educativa de 1-2 oraciones clínicamente precisa
- Las opciones incorrectas deben ser plausibles pero claramente erróneas
- Las preguntas deben ser relevantes para prevención de lumbalgia en contexto universitario
- No incluyas numeración ni texto fuera del JSON
- No uses markdown ni bloques de código";

        $body = json_encode([
            'model'       => $this->model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Eres un experto en salud ocupacional, ergonomía y lumbalgia. Generas contenido educativo médicamente preciso. Respondes únicamente con JSON válido sin ningún texto adicional, sin markdown, sin bloques de código.'
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens'  => 2048,
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new \Exception(
                'Error de Groq API: HTTP ' . $httpCode . ' — ' .
                ($error['error']['message'] ?? $response)
            );
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Limpiar posibles bloques markdown que el modelo agregue
        $content = preg_replace('/```json|```/i', '', $content);
        $content = trim($content);

        $questions = json_decode($content, true);

        if (!is_array($questions)) {
            throw new \Exception('La respuesta no es un JSON válido: ' . substr($content, 0, 200));
        }

        return $questions;
    }
}