// src/app/core/services/profile.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { StudentProfile } from '../../models/user.model';

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getProfile() {
    return this.http.get<{ data: StudentProfile }>(`${this.api}/profile`);
  }
}
