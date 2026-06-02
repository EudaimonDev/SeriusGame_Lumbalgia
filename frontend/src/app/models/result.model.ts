// src/app/models/result.model.ts
export interface TestResult {
  id: number;
  user_id: number;
  test_type: 'pretest' | 'posttest';
  score: number;
  knowledge_gain: number | null;
  applied_at: string;
}

export interface MyResultsResponse {
  results: TestResult[];
  knowledge_gain: number | null;
  pretest_score: number | null;
  posttest_score: number | null;
}

export interface GroupReport {
  control:      GroupStat;
  experimental: GroupStat;
}

export interface GroupStat {
  pretest:  { avg_score: number; total: number } | null;
  posttest: { avg_score: number; total: number } | null;
  gain:     number | null;
}

export interface SessionEvolution {
  user_id: number;
  student_name: string;
  group_type: 'experimental' | 'control';
  room_name: string;
  session_number: number;
  score: number;
  total_questions: number;
  precision: number;
  started_at: string;
}

export interface StudentStat {
  user_id: number;
  student_name: string;
  group_type: 'experimental' | 'control';
  room_name: string;
  total_sessions: number;
  avg_score: number;
  avg_precision: number;
  max_score: number;
  scores: number[]; // para mediana y desviación en frontend
}

export interface TestComparison {
  user_id: number;
  student_name: string;
  group_type: 'experimental' | 'control';
  room_name: string;
  room_id: number;
  pretest_score: number | null;
  posttest_score: number | null;
  knowledge_gain: number | null;
}
