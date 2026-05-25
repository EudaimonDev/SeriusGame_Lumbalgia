// src/app/core/services/auth.service.ts
import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { AuthResponse, User } from '../../models/user.model';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private api = environment.apiUrl;
  currentUser = signal<User | null>(this.loadUser());

  constructor(private http: HttpClient, private router: Router) {}

  // Estudiante — solo nombre y edad
  studentLogin(name: string, age: number) {
    return this.http.post<{ data: AuthResponse }>(`${this.api}/auth/student`, { name, age })
      .pipe(tap(res => this.saveSession(res.data)));
  }

  // Admin — email y password
  adminLogin(email: string, password: string) {
    return this.http.post<{ data: AuthResponse }>(`${this.api}/auth/admin/login`, { email, password })
      .pipe(tap(res => this.saveSession(res.data)));
  }

  // Recuperar contraseña
  forgotPassword(email: string) {
    return this.http.post<{ message: string }>(`${this.api}/auth/forgot-password`, { email });
  }

  logout(): void {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    this.currentUser.set(null);
    this.router.navigate(['/login']);
  }

  getToken():   string | null { return localStorage.getItem('token'); }
  isLoggedIn(): boolean       { return !!this.getToken(); }
  isAdmin():    boolean       { return this.currentUser()?.role === 'admin'; }
  isStudent():  boolean       { return this.currentUser()?.role === 'student'; }

  private saveSession(data: AuthResponse): void {
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    this.currentUser.set(data.user);
  }

  private loadUser(): User | null {
    const raw = localStorage.getItem('user');
    return raw ? JSON.parse(raw) : null;
  }

  joinRoom(name: string, code: string) {
    return this.http.post<{ data: AuthResponse }>(`${this.api}/rooms/join`, { name, code })
      .pipe(tap(res => this.saveSession(res.data)));
  }
}
