import { Component, inject, OnInit, OnDestroy, signal, computed } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { GameService } from '../../../core/services/game.service';
import { Question } from '../../../models/question.model';
import { AnswerResponse } from '../../../models/session.model';
import { TranslocoModule } from '@jsverse/transloco';
import { LanguageService } from '../../../core/services/language.service';

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
  private langSvc = inject(LanguageService);

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
  showFeedback = signal(false);
  feedbackData = signal<any>(null);
  streak       = signal<number>(0);          // ← racha
  showStreakMsg = signal<boolean>(false);     // ← mensaje flotante de racha
  streakMsg    = signal<string>('');         // ← texto del mensaje

  dotsArray    = computed(() => Array.from({ length: this.maxQuestions() }, (_, i) => i + 1));
  heroPosition = computed(() => Math.min((this.answered() / this.maxQuestions()) * 90, 90));
  timerPct     = computed(() => (this.timeLeft() / this.timeSeconds()) * 100);
  heartsArray  = computed(() => Array(this.maxLives()).fill(0));

  // Multiplicador de puntos según racha
  streakMultiplier = computed(() => {
    const s = this.streak();
    if (s >= 5) return 'x2';
    if (s >= 3) return 'x1.5';
    return null;
  });

  private correctSound  = new Audio('correct-music.mp3');
  private errorSound    = new Audio('error-music.mp3');
  private gameOverSound = new Audio('game-over-music.mp3');

  startedAt        = 0;
  private interval: any;
  private streakMsgTimeout: any;

  ngOnInit(): void {
    document.body.classList.add('game-fullscreen');
    window.addEventListener('beforeunload', this.onBeforeUnload);

    const saved = sessionStorage.getItem('gameState');

    if (saved) {
      const state = JSON.parse(saved);
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
        this.streak.set(state.streak ?? 0);  // ← restaurar racha
        this.loading.set(false);
        this.startTimer();
        return;
      }
    }

    sessionStorage.removeItem('gameState');
    console.log('iniciando sesión con idioma:', this.langSvc.currentLang());
    this.gameSvc.startSession(this.langSvc.currentLang()).subscribe({
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
    if (this.submitting()) return;
    this.selected.set(opt);
  }

  submit(): void {
    const opt = this.selected();
    if (!opt || this.submitting()) return;
    this.submitting.set(true);

    const elapsed = Date.now() - this.startedAt;

    this.gameSvc.sendAnswer(
      this.sessionId(),
      this.question()!.id,
      opt,
      elapsed,
      this.lives(),
      this.langSvc.currentLang()
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
    console.log('timeout triggered', { selected: this.selected(), submitting: this.submitting() });
    if (this.selected() || this.submitting()) return;
    this.submitting.set(true);
    this.selected.set('__timeout__');

    const elapsed = this.timeSeconds() * 1000;

    this.gameSvc.sendAnswer(
      this.sessionId(),
      this.question()!.id,
      'a',
      elapsed,
      this.lives(),
      this.langSvc.currentLang()
    ).subscribe({
      next: res => {
        console.log('timeout response', res.data);
        const data = res.data;
        this.score.update(s => s + (data.points_earned ?? 0));
        this.answered.update(n => n + 1);

        if (data.lives_remaining !== undefined) {
          this.lives.set(data.lives_remaining);
        }
        if (data.new_difficulty) this.difficulty.set(data.new_difficulty);

        // Timeout siempre resetea racha
        this.streak.set(0);

        this.errorSound.currentTime = 0;
        this.errorSound.volume = 0.6;
        this.errorSound.play().catch(() => {});

        if (data.game_over) {
          sessionStorage.removeItem('gameState');
          this.gameOverSound.currentTime = 0;
          this.gameOverSound.volume = 0.7;
          this.gameOverSound.play().catch(() => {});

          setTimeout(() => {
            if (data.reason === 'no_lives') {
              sessionStorage.setItem('gameResult', 'defeat');
              sessionStorage.setItem('defeatStats', JSON.stringify({
                score:      data.score ?? 0,
                total:      data.total ?? 0,
                percentage: data.percentage ?? 0
              }));
              this.router.navigate(['/ranking']);
            } else {
              this.router.navigate(['/result'], {
                queryParams: { session_id: this.sessionId() }
              });
            }
          }, 200);
        } else {
          // ← Guardar gameState con la siguiente pregunta
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
            streak:       this.streak(),
          }));
          this.feedbackData.set({
            ...data,
            selected:     '__timeout__',
            questionText: this.question()?.question_text,
            livesLeft:    this.lives(),
            score:        this.score(),
            answered:     this.answered(),
            maxQuestions: this.maxQuestions(),
            streak:       this.streak(),
          });
          this.showFeedback.set(true);
        }
      },
      error: (e) => {
        console.error('timeout error', e);
        this.submitting.set(false);
      }
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

    // Actualizar racha y mostrar mensaje
    const prevStreak = this.streak();
    this.streak.set(data.streak ?? 0);
    this.showStreakNotification(data.streak ?? 0, prevStreak, data.correct);

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
      streak:       this.streak(),
    }));

    if (data.game_over) {
      sessionStorage.removeItem('gameState');
      this.gameOverSound.currentTime = 0;
      this.gameOverSound.volume = 0.7;
      this.gameOverSound.play().catch(() => {});

      if (data.reason === 'no_lives') {
        sessionStorage.setItem('gameResult', 'defeat');
        sessionStorage.setItem('defeatStats', JSON.stringify({
          score:      data.score ?? 0,
          total:      data.total ?? 0,
          percentage: data.percentage ?? 0
        }));
        setTimeout(() => this.router.navigate(['/ranking']), 200);
      } else {
        setTimeout(() => this.router.navigate(['/result'], {
          queryParams: { session_id: this.sessionId() }
        }), 200);
      }
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
        streak:       this.streak(),  // ← guardar racha
      }));
      this.feedbackData.set({
        ...data,
        selected:     this.selected(),
        questionText: this.question()?.question_text,
        livesLeft:    this.lives(),
        score:        this.score(),
        answered:     this.answered(),
        maxQuestions: this.maxQuestions(),
        streak:       this.streak(),
      });
      this.showFeedback.set(true);
    }
  }

  // Muestra mensaje flotante cuando se activa o sube la racha
  private showStreakNotification(newStreak: number, prevStreak: number, correct: boolean): void {
    if (!correct) return;

    let msg = '';
    if (newStreak === 3) msg = '🔥 ¡Racha x3! +50% puntos';
    else if (newStreak === 5) msg = '⚡ ¡Racha x5! +100% puntos';
    else if (newStreak > 5 && newStreak % 5 === 0) msg = `🌟 ¡Racha x${newStreak}!`;
    else if (newStreak > 0 && newStreak % 3 === 0 && newStreak > prevStreak) msg = `🔥 ¡Racha x${newStreak}!`;

    if (!msg) return;

    clearTimeout(this.streakMsgTimeout);
    this.streakMsg.set(msg);
    this.showStreakMsg.set(true);
    this.streakMsgTimeout = setTimeout(() => this.showStreakMsg.set(false), 2000);
  }

  continueFeedback(): void {
    const d = this.feedbackData();
    this.showFeedback.set(false);
    this.feedbackData.set(null);
    this.selected.set('');
    this.submitting.set(false);

    if (d?.game_over) {
      sessionStorage.removeItem('gameState');
      if (d.reason === 'no_lives') {
        sessionStorage.setItem('gameResult', 'defeat');
        this.router.navigate(['/ranking']);
      } else {
        this.router.navigate(['/result'], {
          queryParams: { session_id: this.sessionId() }
        });
      }
    } else {
      this.question.set(d.next_question);
      this.startTimer();
    }
  }

  getOption(opt: string): string {
    const q = this.question();
    if (!q) return '';
    const key = `option_${opt}` as keyof Question;
    return q[key] as string;
  }

  private onBeforeUnload = (event: BeforeUnloadEvent): void => {
    sessionStorage.removeItem('gameState');
    sessionStorage.removeItem('feedback');
    event.preventDefault();
    event.returnValue = '¿Seguro que deseas salir? Perderás el progreso de la partida actual.';
  };

  ngOnDestroy(): void {
    document.body.classList.remove('game-fullscreen');
    clearInterval(this.interval);
    clearTimeout(this.streakMsgTimeout);
    window.removeEventListener('beforeunload', this.onBeforeUnload);
  }
}
