export interface User {
  id:          number;
  name:        string;
  age?:        number;
  email?:      string;
  role:        'admin' | 'student';
  group_type:  'control' | 'experimental';
  total_score: number;
  total_games: number;
  best_streak: number;
  created_at:  string;
}

export interface AuthResponse {
  token: string;
  user:  User;
}

export interface StudentProfile {
  user: {
    id:         number;
    name:       string;
    age:        number;
    role:       string;
    created_at: string;
  };
  stats: {
    total_games:  number;
    total_score:  number;
    precision:    number;
    avg_time_sec: number;
    best_score:   number;
  };
  errors: {
    question_text:  string;
    selected_answer: string;
    correct_answer: string;
    feedback_text:  string;
    category_name:  string;
  }[];
  categories: {
    category:  string;
    total:     number;
    correct:   number;
    precision: number;
    avg_time:  number;
  }[];
}
