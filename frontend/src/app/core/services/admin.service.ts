// src/app/core/services/admin.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AdminQuestion } from '../../models/question.model';

@Injectable({ providedIn: 'root' })
export class AdminService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getQuestions(filters: any = {}) {
    return this.http.get<{ data: AdminQuestion[] }>(`${this.api}/admin/questions`, { params: filters });
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

  getCategories() {
    return this.http.get<{ data: any[] }>(`${this.api}/admin/categories`);
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
}
