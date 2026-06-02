// src/app/models/session.model.ts
export interface GameSession {
  id:                 number;
  user_id:            number;
  session_type:       'pretest' | 'game' | 'posttest';
  current_difficulty: 'easy' | 'medium' | 'hard';
  score:              number;
  total_questions:    number;
  started_at:         string;
  ended_at:           string | null;
}

export interface StartSessionResponse {
  session_id: number;
  question:   import('./question.model').Question;
  config: {
    lives:        number;
    questions:    number;
    time_seconds: number;
  };
}

export interface AnswerResponse {
  feedback:        string;
  correct:         boolean;
  correct_answer:  string;
  game_over:       boolean;
  points_earned:   number;
  lives_remaining?: number;
  reason?:         'no_lives' | 'completed';
  new_difficulty?: 'easy' | 'medium' | 'hard';
  next_question?:  import('./question.model').Question;
  score?:          number;
  total?:          number;
  percentage?:     number;
  streak?:         number;
}
