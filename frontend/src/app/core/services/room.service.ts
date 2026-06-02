import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface Room {
  id:              number;
  code:            string;
  name:            string;
  group_type:      string;
  is_active:       boolean;
  //phase:           'pretest' | 'game' | 'posttest';
  questions_count: number;
  difficulty:      'easy' | 'medium' | 'hard' | 'adaptive';
  category_ids:    string | null;
  total_students:  number;
}

@Injectable({ providedIn: 'root' })
export class RoomService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getRooms() {
    return this.http.get<{ data: Room[] }>(`${this.api}/admin/rooms`);
  }

  createRoom(data: {
    code:            string;
    name:            string;
    group_type:      string;
    // phase?:       string;  // ── FASE COMENTADO
    questions_count?: number;
    difficulty?:     string;
    category_ids?:   string | null;
  }) {
    return this.http.post<{ data: Room }>(`${this.api}/admin/rooms`, data);
  }

  updateRoom(id: number, data: {
    name:            string;
    group_type:      string;
    //phase:           string;
    questions_count: number;
    difficulty:      string;
    category_ids?:   string | null;
  }) {
    return this.http.put<{ data: Room }>(`${this.api}/admin/rooms/${id}`, data);
  }

  toggleRoom(id: number) {
    return this.http.patch<{ data: Room }>(`${this.api}/admin/rooms/${id}/toggle`, {});
  }

  deleteRoom(id: number) {
    return this.http.delete<{ data: null }>(`${this.api}/admin/rooms/${id}`);
  }

  //updatePhase(id: number, phase: 'pretest' | 'game' | 'posttest') {
  //  return this.http.patch<{ data: Room }>(`${this.api}/admin/rooms/${id}/phase`, { phase });
  //}
}
