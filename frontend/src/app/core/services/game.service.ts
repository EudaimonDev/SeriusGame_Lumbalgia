// src/app/core/services/game.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { StartSessionResponse, AnswerResponse } from '../../models/session.model';

@Injectable({ providedIn: 'root' })
export class GameService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  startSession(language: string = 'es') {
  //startSession(type: string, language: string = 'es') {
  return this.http.post<{ data: any }>(`${this.api}/game/start`, {
    session_type: 'game', // ── TIPO FIJO POR AHORA',
    language
  });
}

  sendAnswer(
    sessionId:      number,
    questionId:     number,
    selectedAnswer: string,
    responseTimeMs: number,
    livesLeft:      number,
    language:       string
  ) {
    return this.http.post<{ data: AnswerResponse }>(`${this.api}/game/answer`, {
      session_id:       sessionId,
      question_id:      questionId,
      selected_answer:  selectedAnswer,
      response_time_ms: responseTimeMs,
      lives_left:       livesLeft,
      language
    });
  }

  getResult(sessionId: number) {
    return this.http.get<{ data: any }>(`${this.api}/game/result`, {
      params: { session_id: sessionId.toString() }
    });
  }
}
