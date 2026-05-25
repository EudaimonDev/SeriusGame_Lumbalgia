import { Component, inject, OnInit, OnDestroy, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { TranslocoModule } from '@jsverse/transloco';
import { AuthService } from '../../../core/services/auth.service';
import { GameConfigService } from '../../../core/services/game-config.service';
import { LanguageService } from '../../../core/services/language.service';
import { RankingItem } from '../../../models/game-config.model';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, TranslocoModule],
  templateUrl: './login.component.html'
})
export class LoginComponent implements OnInit, OnDestroy {
  private fb        = inject(FormBuilder);
  private auth      = inject(AuthService);
  private router    = inject(Router);
  private configSvc = inject(GameConfigService);
  langSvc           = inject(LanguageService);

  showRanking   = signal(false);
  showRoomForm  = signal(false);
  showHowToPlay = signal(false);
  top3          = signal<RankingItem[]>([]);
  muted         = false;
  error         = '';
  roomError     = '';
  loading       = false;
  roomLoading   = false;

  form = this.fb.group({
    name: ['', [Validators.required, Validators.pattern(/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/)]],
    age:  ['', [Validators.required, Validators.min(16), Validators.max(60), Validators.pattern(/^[0-9]+$/)]]
  });

  roomForm = this.fb.group({
    name: ['', [Validators.required, Validators.pattern(/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/)]],
    code: ['', Validators.required]
  });

  private audio: HTMLAudioElement | null = null;

  ngOnInit(): void {
    this.configSvc.getRanking().subscribe({
      next: res => this.top3.set(res.data.ranking.slice(0, 3))
    });

    this.audio = new Audio('login-music.mp3');
    this.audio.loop   = true;
    this.audio.volume = 0.4;
    this.audio.play().catch(() => {
      const unlock = () => { this.audio?.play(); };
      document.addEventListener('click',      unlock, { once: true });
      document.addEventListener('keydown',    unlock, { once: true });
      document.addEventListener('touchstart', unlock, { once: true });
    });
  }

  ngOnDestroy(): void {
    if (this.audio) {
      this.audio.pause();
      this.audio.src = '';
      this.audio = null;
    }
  }

  toggleMute(): void {
    if (this.audio) {
      this.muted = !this.muted;
      this.audio.muted = this.muted;
    }
  }

  submit(): void {
    if (this.form.invalid) return;
    this.loading = true;
    this.error   = '';
    const name = this.form.value.name!;
    const age  = Number(this.form.value.age);
    this.auth.studentLogin(name, age).subscribe({
      next:  () => this.router.navigate(['/game']),
      error: (e: any) => {
        this.error   = e.error?.message ?? 'Error al ingresar';
        this.loading = false;
      }
    });
  }

  submitRoom(): void {
    if (this.roomForm.invalid) return;
    this.roomLoading = true;
    this.roomError   = '';
    const { name, code } = this.roomForm.value;
    this.auth.joinRoom(name!, code!).subscribe({
      next:  () => this.router.navigate(['/game']),
      error: (e: any) => {
        this.roomError   = e.error?.message ?? 'Código inválido';
        this.roomLoading = false;
      }
    });
  }

  soloLetras(event: KeyboardEvent): void {
    if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]$/.test(event.key)) {
      event.preventDefault();
    }
  }

  soloNumeros(event: KeyboardEvent): void {
    if (!/[0-9]/.test(event.key)) {
      event.preventDefault();
    }
  }

  getMedal(pos: number): string {
    if (pos === 1) return '🥇';
    if (pos === 2) return '🥈';
    return '🥉';
  }
}
