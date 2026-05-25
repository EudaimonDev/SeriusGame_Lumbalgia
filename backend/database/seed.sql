USE lumbalgia_db;

INSERT INTO users (name, email, password, role) VALUES
('Administrador', 'admin@lumbalgia.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
 'admin');

INSERT INTO categories (name, description) VALUES
('Ergonomía postural',   'Conocimientos sobre postura correcta frente al computador'),
('Lumbalgia general',    'Causas, síntomas y prevención del dolor lumbar'),
('Pausas activas',       'Ejercicios y rutinas para prevenir lesiones'),
('Hábitos saludables',  'Rutinas y comportamientos que protegen la columna');

INSERT INTO questions (category_id, difficulty, question_text, option_a, option_b, option_c, option_d, correct_answer, feedback_text) VALUES
(1, 'easy',
 '¿Cuál es el ángulo recomendado para los codos al escribir en el teclado?',
 '45 grados', '90 grados', '120 grados', '60 grados',
 'b',
 'Los codos deben formar un ángulo de aproximadamente 90° para evitar tensión en antebrazos y muñecas.'),

(1, 'easy',
 '¿A qué altura debe estar la pantalla del monitor respecto a los ojos?',
 'Por encima del nivel de los ojos', 'Al nivel de los ojos o ligeramente por debajo',
 'A la altura del pecho', 'No importa la altura',
 'b',
 'La parte superior del monitor debe estar al nivel de los ojos o ligeramente por debajo para evitar tensión cervical.'),

(2, 'medium',
 '¿Cuál es la causa más frecuente de lumbalgia en estudiantes universitarios?',
 'Mala alimentación', 'Sedentarismo y mala postura prolongada',
 'Exceso de ejercicio físico', 'Problemas genéticos',
 'b',
 'El sedentarismo y las malas posturas durante largas horas frente al computador son la causa principal de lumbalgia en estudiantes.'),

(3, 'easy',
 '¿Con qué frecuencia se recomienda hacer pausas activas?',
 'Una vez al día', 'Cada 2 horas', 'Cada 45-60 minutos', 'Solo cuando hay dolor',
 'c',
 'Se recomienda realizar pausas activas cada 45-60 minutos para relajar la musculatura y reactivar la circulación.');