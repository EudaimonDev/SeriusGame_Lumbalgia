// src/app/models/question.model.ts
export interface Question {
  id: number;
  question_text: string;
  option_a: string;
  option_b: string;
  option_c: string;
  option_d: string;
  difficulty: 'easy' | 'medium' | 'hard';
  category_id: number;
}

export interface AdminQuestion extends Question {
  correct_answer: string;
  feedback_text: string;
  is_active: boolean;
  category_name?: string;
}
