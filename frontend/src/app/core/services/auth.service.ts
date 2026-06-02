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
  currentRoom = signal<any | null>(this.loadRoom());

  constructor(private http: HttpClient, private router: Router) {}

  studentLogin(name: string, age: number) {
    return this.http.post<{ data: AuthResponse }>(`${this.api}/auth/student`, { name, age })
      .pipe(tap(res => this.saveSession(res.data)));
  }

  adminLogin(email: string, password: string) {
    return this.http.post<{ data: AuthResponse }>(`${this.api}/auth/admin/login`, { email, password })
      .pipe(tap(res => this.saveSession(res.data)));
  }

  forgotPassword(email: string) {
    return this.http.post<{ message: string }>(`${this.api}/auth/forgot-password`, { email });
  }

  joinRoom(name: string, code: string, age: number) {
    return this.http.post<{ data: any }>(`${this.api}/rooms/join`, { name, code, age })
      .pipe(tap(res => {
        // Limpiar sesión de juego anterior
        sessionStorage.removeItem('gameState');
        sessionStorage.removeItem('feedback');
        sessionStorage.removeItem('gameResult');
        sessionStorage.removeItem('defeatStats');
        this.saveSession(res.data);
        // Guardar datos de la sala incluyendo la fase
        if (res.data.room) {
          localStorage.setItem('room', JSON.stringify(res.data.room));
          this.currentRoom.set(res.data.room);
        }
      }));
  }

  logout(): void {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('room');
    sessionStorage.removeItem('gameState');
    sessionStorage.removeItem('feedback');
    sessionStorage.removeItem('gameResult');
    sessionStorage.removeItem('defeatStats');
    this.currentUser.set(null);
    this.currentRoom.set(null);
    sessionStorage.removeItem('testMode');
    this.router.navigate(['/login']);
  }

  // Devuelve la fase activa de la sala del estudiante
  getRoomPhase(): 'pretest' | 'game' | 'posttest' {
    return this.currentRoom()?.phase ?? 'game';
  }

  // Verifica si el estudiante tiene sala asignada
  hasRoom(): boolean {
    return !!this.currentRoom();
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

  private loadRoom(): any | null {
    const raw = localStorage.getItem('room');
    return raw ? JSON.parse(raw) : null;
  }
}
