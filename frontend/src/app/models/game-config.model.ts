// src/app/models/game-config.model.ts
export interface GameConfig {
  id:             number;
  lives:          number;
  questions:      number;
  time_seconds:   number;
  points_correct: number;
  points_bonus:   number;
}

export interface RankingItem {
  position:       number;
  id:             number;
  name:           string;
  age:            number;
  partidas:       number;
  max_puntaje:    number;
  puntos_totales: number;
  precision:      number;
}

export interface RankingResponse {
  ranking:     RankingItem[];
  my_position: RankingItem | null;
  total:       number;
}
