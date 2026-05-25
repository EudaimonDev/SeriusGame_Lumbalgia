// src/app/core/services/room.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface Room {
  id:             number;
  code:           string;
  name:           string;
  group_type:     'control' | 'experimental';
  is_active:      boolean;
  total_students: number;
  created_at:     string;
}

@Injectable({ providedIn: 'root' })
export class RoomService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getRooms() {
    return this.http.get<{ data: Room[] }>(`${this.api}/admin/rooms`);
  }

  createRoom(data: { code: string; name: string; group_type: string }) {
    return this.http.post<{ data: Room }>(`${this.api}/admin/rooms`, data);
  }

  toggleRoom(id: number) {
    return this.http.patch<{ data: Room }>(`${this.api}/admin/rooms/${id}/toggle`, {});
  }

  deleteRoom(id: number) {
    return this.http.delete<{ data: null }>(`${this.api}/admin/rooms/${id}`);
  }
}
