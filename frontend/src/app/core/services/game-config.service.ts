// src/app/core/services/game-config.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { GameConfig, RankingResponse } from '../../models/game-config.model';

@Injectable({ providedIn: 'root' })
export class GameConfigService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getConfig() {
    return this.http.get<{ data: GameConfig }>(`${this.api}/config`);
  }

  updateConfig(data: Partial<GameConfig>) {
    return this.http.put<{ data: GameConfig }>(`${this.api}/admin/config`, data);
  }

  getRanking() {
    return this.http.get<{ data: RankingResponse }>(`${this.api}/ranking`);
  }
}
