// src/app/core/services/result.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { MyResultsResponse, GroupReport } from '../../models/result.model';

@Injectable({ providedIn: 'root' })
export class ResultService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getMyResults() {
    return this.http.get<{ data: MyResultsResponse }>(`${this.api}/results/me`);
  }

  getGroupReport() {
    return this.http.get<{ data: GroupReport }>(`${this.api}/results/group`);
  }
}
