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
