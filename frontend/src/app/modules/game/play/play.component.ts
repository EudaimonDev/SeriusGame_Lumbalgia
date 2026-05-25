import { Component, inject, OnInit, OnDestroy, signal, computed } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { GameService } from '../../../core/services/game.service';
import { Question } from '../../../models/question.model';
import { AnswerResponse } from '../../../models/session.model';
import { TranslocoModule } from '@jsverse/transloco';


@Component({
  selector: 'app-play',
  standalone: true,
  imports: [TranslocoModule],
  templateUrl: './play.component.html'
})
export class PlayComponent implements OnInit, OnDestroy {
  private gameSvc = inject(GameService);
  private router  = inject(Router);
  auth            = inject(AuthService);

  sessionId    = signal<number>(0);
  question     = signal<Question | null>(null);
  selected     = signal<string>('');
  loading      = signal<boolean>(true);
  submitting   = signal<boolean>(false);
  maxLives     = signal<number>(3);
  maxQuestions = signal<number>(16);
  timeSeconds  = signal<number>(15);
  lives        = signal<number>(3);
  score        = signal<number>(0);
  answered     = signal<number>(0);
  timeLeft     = signal<number>(15);
  difficulty   = signal<string>('easy');
  paused       = signal(false);

  dotsArray    = computed(() => Array.from({ length: this.maxQuestions() }, (_, i) => i + 1));
  heroPosition = computed(() => Math.min((this.answered() / this.maxQuestions()) * 90, 90));
  timerPct     = computed(() => (this.timeLeft() / this.timeSeconds()) * 100);
  heartsArray  = computed(() => Array(this.maxLives()).fill(0));
  private correctSound = new Audio('correct-music.mp3');
  private errorSound   = new Audio('error-music.mp3');
  private gameOverSound = new Audio('game-over-music.mp3');


  startedAt        = 0;
  private interval: any;

  ngOnInit(): void {
    window.addEventListener('beforeunload', this.onBeforeUnload);
    // Intentar restaurar sesión existente (viene del feedback)
    const saved = sessionStorage.getItem('gameState');

    if (saved) {
      const state = JSON.parse(saved);

      // Verificar que la siguiente pregunta existe
      if (state.nextQuestion) {
        this.sessionId.set(state.sessionId);
        this.maxLives.set(state.maxLives);
        this.maxQuestions.set(state.maxQuestions);
        this.timeSeconds.set(state.timeSeconds);
        this.lives.set(state.lives);
        this.score.set(state.score);
        this.answered.set(state.answered);
        this.difficulty.set(state.difficulty);
        this.question.set(state.nextQuestion);
        this.loading.set(false);
        this.startTimer();
        return;
      }
    }



    // Nueva sesión
    sessionStorage.removeItem('gameState');
    this.gameSvc.startSession('game').subscribe({
      next: res => {
        this.sessionId.set(res.data.session_id);
        this.question.set(res.data.question);
        this.difficulty.set(res.data.question.difficulty);

        const cfg = res.data.config;
        this.maxLives.set(cfg.lives);
        this.maxQuestions.set(cfg.questions);
        this.timeSeconds.set(cfg.time_seconds);
        this.lives.set(cfg.lives);
        this.timeLeft.set(cfg.time_seconds);

        this.loading.set(false);
        this.startTimer();
      },
      error: () => this.router.navigate(['/login'])
    });
  }

  togglePause(): void {
    this.paused.update(p => !p);
    if (this.paused()) {
      clearInterval(this.interval);
    } else {
      this.startTimer();
    }
  }

  exitGame(): void {
    sessionStorage.removeItem('gameState');
    sessionStorage.removeItem('feedback');
    this.router.navigate(['/login']);
  }

  select(opt: string): void {
    if (this.selected() || this.submitting()) return;
    this.selected.set(opt);
    clearInterval(this.interval);

    const elapsed = Date.now() - this.startedAt;
    this.submitting.set(true);

    this.gameSvc.sendAnswer(
      this.sessionId(),
      this.question()!.id,
      opt,
      elapsed,
      this.lives()  // enviamos las vidas actuales, backend resta si es incorrecta
    ).subscribe({
      next: res => this.handleAnswer(res.data),
      error: () => this.submitting.set(false)
    });
  }

private startTimer(): void {
  this.timeLeft.set(this.timeSeconds());
  this.startedAt = Date.now();
  clearInterval(this.interval);

  this.interval = setInterval(() => {
    this.timeLeft.update(t => {
      if (t <= 1) {
        clearInterval(this.interval);
        this.onTimeout();
        return 0;
      }
      return t - 1;
    });
  }, 1000);
}

private onTimeout(): void {
  if (this.selected() || this.submitting()) return;
  this.submitting.set(true);
  this.selected.set('__timeout__');

  const elapsed = this.timeSeconds() * 1000;

  this.gameSvc.sendAnswer(
    this.sessionId(),
    this.question()!.id,
    'a',
    elapsed,
    this.lives()
  ).subscribe({
    next: res => {
      const data = res.data;
      this.score.update(s => s + (data.points_earned ?? 0));
      this.answered.update(n => n + 1);

      if (data.lives_remaining !== undefined) {
        this.lives.set(data.lives_remaining);
      }

      if (data.new_difficulty) this.difficulty.set(data.new_difficulty);

      // Sonido error por timeout
      this.errorSound.currentTime = 0;
      this.errorSound.volume = 0.6;
      this.errorSound.play().catch(() => {});

      sessionStorage.setItem('feedback', JSON.stringify({
        ...data,
        selected:     '__timeout__',
        questionText: this.question()?.question_text,
        livesLeft:    this.lives(),
        score:        this.score(),
        answered:     this.answered(),
        maxQuestions: this.maxQuestions(),
      }));

      if (data.game_over) {
        sessionStorage.removeItem('gameState');

        this.gameOverSound.currentTime = 0;
        this.gameOverSound.volume = 0.7;
        this.gameOverSound.play().catch(() => {});

        setTimeout(() => {
          if (data.reason === 'no_lives') {
            sessionStorage.setItem('gameResult', 'defeat');  // ← agrega esto
            this.gameOverSound.currentTime = 0;
            this.gameOverSound.volume = 0.7;
            this.gameOverSound.play().catch(() => {});
            this.router.navigate(['/ranking']);
          } else {
            this.router.navigate(['/result'], {
              queryParams: { session_id: this.sessionId() }
            });
          }
        }, 200);
      } else {
        sessionStorage.setItem('gameState', JSON.stringify({
          sessionId:    this.sessionId(),
          maxLives:     this.maxLives(),
          maxQuestions: this.maxQuestions(),
          timeSeconds:  this.timeSeconds(),
          lives:        this.lives(),
          score:        this.score(),
          answered:     this.answered(),
          difficulty:   this.difficulty(),
          nextQuestion: data.next_question,
        }));

        this.router.navigate(['/feedback']);
      }
    },
    error: () => this.submitting.set(false)
  });
}

  private handleAnswer(data: AnswerResponse): void {
  this.score.update(s => s + (data.points_earned ?? 0));
  this.answered.update(n => n + 1);

  if (data.lives_remaining !== undefined) {
    this.lives.set(data.lives_remaining);
  }

  if (data.new_difficulty) {
    this.difficulty.set(data.new_difficulty);
  }

  // Sonidos
  if (data.correct) {
    this.correctSound.currentTime = 0;
    this.correctSound.volume = 0.6;
    this.correctSound.play().catch(() => {});
  } else {
    this.errorSound.currentTime = 0;
    this.errorSound.volume = 0.6;
    this.errorSound.play().catch(() => {});
  }

  sessionStorage.setItem('feedback', JSON.stringify({
    ...data,
    selected:     this.selected(),
    questionText: this.question()?.question_text,
    livesLeft:    this.lives(),
    score:        this.score(),
    answered:     this.answered(),
    maxQuestions: this.maxQuestions(),
  }));

  if (data.game_over) {
    sessionStorage.removeItem('gameState');

    this.gameOverSound.currentTime = 0;
    this.gameOverSound.volume = 0.7;
    this.gameOverSound.play().catch(() => {});

    setTimeout(() => {
      if (data.reason === 'no_lives') {
        sessionStorage.setItem('gameResult', 'defeat');  // ← agrega esto
        this.gameOverSound.currentTime = 0;
        this.gameOverSound.volume = 0.7;
        this.gameOverSound.play().catch(() => {});
        this.router.navigate(['/ranking']);
      } else {
        this.router.navigate(['/result'], {
          queryParams: { session_id: this.sessionId() }
        });
      }
    }, 200);
  } else {
    sessionStorage.setItem('gameState', JSON.stringify({
      sessionId:    this.sessionId(),
      maxLives:     this.maxLives(),
      maxQuestions: this.maxQuestions(),
      timeSeconds:  this.timeSeconds(),
      lives:        this.lives(),
      score:        this.score(),
      answered:     this.answered(),
      difficulty:   this.difficulty(),
      nextQuestion: data.next_question,
    }));
    setTimeout(() => this.router.navigate(['/feedback']), 200);
  }
}


  getOption(opt: string): string {
    const q = this.question();
    if (!q) return '';
    const key = `option_${opt}` as keyof Question;
    return q[key] as string;
  }

  private onBeforeUnload = (event: BeforeUnloadEvent): void => {
    // Limpiar estado del juego para que al volver empiece de nuevo
    sessionStorage.removeItem('gameState');
    sessionStorage.removeItem('feedback');

    // Mostrar alerta del navegador
    event.preventDefault();
    event.returnValue = '¿Seguro que deseas salir? Perderás el progreso de la partida actual.';
  };

  ngOnDestroy(): void {
    clearInterval(this.interval);
    window.removeEventListener('beforeunload', this.onBeforeUnload);
  }
}
