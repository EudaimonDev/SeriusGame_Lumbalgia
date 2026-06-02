// src/app/core/services/admin.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AdminQuestion } from '../../models/question.model';
import { SessionEvolution, StudentStat,TestComparison } from '../../models/result.model'; // ← agrega esta línea
@Injectable({ providedIn: 'root' })
export class AdminService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getQuestions(filters: any = {}) {
    return this.http.get<{ data: AdminQuestion[] }>(
      `${this.api}/admin/questions`,
      { params: filters }
    );
  }

  getReportQuestions(roomId?: number) {
    const params: Record<string, string> = {};
    if (roomId) params['room_id'] = roomId.toString();
    return this.http.get<{ data: any[] }>(`${this.api}/admin/reports/questions`, { params });
  }

  createQuestion(data: any) {
    return this.http.post<{ data: AdminQuestion }>(`${this.api}/admin/questions`, data);
  }

  updateQuestion(id: number, data: any) {
    return this.http.put<{ data: AdminQuestion }>(`${this.api}/admin/questions/${id}`, data);
  }

  deleteQuestion(id: number) {
    return this.http.delete<{ data: null }>(`${this.api}/admin/questions/${id}`);
  }

  getCategories(lang?: string) {
    const params: Record<string, string> = {};
    if (lang) params['lang'] = lang;
    return this.http.get<{ data: any[] }>(`${this.api}/admin/categories`, { params });
  }

  createCategory(data: { name: string; description: string }) {
  return this.http.post<{ data: any }>(`${this.api}/admin/categories`, data);
}

updateCategory(id: number, data: { name: string; description: string }) {
  return this.http.put<{ data: any }>(`${this.api}/admin/categories/${id}`, data);
}

deleteCategory(id: number) {
  return this.http.delete<{ data: null }>(`${this.api}/admin/categories/${id}`);
}

  // ── Nuevos ──

  importQuestions(file: File) {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<{ data: any }>(`${this.api}/admin/questions/import`, formData);
  }

  toggleQuestion(id: number) {
    return this.http.patch<{ data: { id: number; is_active: boolean; status: string } }>(
      `${this.api}/admin/questions/${id}/toggle`, {}
    );
  }

  generateQuestions(data: {
    category_id:   number;
    category_name: string;
    difficulty:    string;
    count:         number;
    language:      string;
  }) {
    return this.http.post<{ data: any }>(`${this.api}/admin/questions/generate`, data);
  }

  getReportRooms() {
  return this.http.get<{ data: any[] }>(`${this.api}/admin/reports/rooms`);
}

getReportStudents(roomId?: number) {
  const params: any = {};
  if (roomId) params['room_id'] = roomId.toString();
  return this.http.get<{ data: any[] }>(`${this.api}/admin/reports/students`, { params });
}

getReportEvolution(roomId?: number) {
  const params: any = {};
  if (roomId) params['room_id'] = roomId.toString();
  return this.http.get<{ data: SessionEvolution[] }>(`${this.api}/admin/reports/evolution`, { params });
}

getReportStats(roomId?: number) {
  const params: any = {};
  if (roomId) params['room_id'] = roomId.toString();
  return this.http.get<{ data: StudentStat[] }>(`${this.api}/admin/reports/stats`, { params });
}

getReportTestComparison() {
  return this.http.get<{ data: TestComparison[] }>(`${this.api}/admin/reports/testcomparison`);
}
}
