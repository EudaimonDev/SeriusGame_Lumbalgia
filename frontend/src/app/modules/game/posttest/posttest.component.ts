/*import { Component, inject, OnInit, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { GameService } from '../../../core/services/game.service';
import { Question } from '../../../models/question.model';
import { LanguageService } from '../../../core/services/language.service';

@Component({
  selector: 'app-posttest',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './posttest.component.html'
})
export class PosttestComponent implements OnInit {
  private gameSvc = inject(GameService);
  private router  = inject(Router);
  private langSvc = inject(LanguageService);
  sessionId  = signal<number>(0);
  question   = signal<Question | null>(null);
  selected   = signal<string>('');
  answered   = signal<number>(0);
  total      = signal<number>(20);
  loading    = signal<boolean>(true);
  finished   = signal<boolean>(false);
  finalScore = signal<number>(0);

  ngOnInit(): void {
    sessionStorage.setItem('testMode', 'posttest');
    this.gameSvc.startSession('posttest', this.langSvc.currentLang()).subscribe({
      next: res => {
        this.sessionId.set(res.data.session_id);
        this.question.set(res.data.question);
        if (res.data.config?.questions) {
          this.total.set(res.data.config.questions);
        }
        this.loading.set(false);
      }
    });
  }

  select(opt: string): void {
    if (this.selected()) return;
    this.selected.set(opt);
    this.gameSvc.sendAnswer(
      this.sessionId(),
      this.question()!.id,
      opt,
      0,
      999
    ).subscribe({ next: res => this.handleAnswer(res.data) });
  }

  getOption(opt: string): string {
    const q = this.question();
    if (!q) return '';
    const key = `option_${opt}` as keyof Question;
    return q[key] as string;
  }

  get progress(): number {
    return (this.answered() / this.total()) * 100;
  }

  private handleAnswer(data: any): void {
    this.answered.update(n => n + 1);

    if (data.game_over) {
      sessionStorage.removeItem('testMode');
      this.finalScore.set(data.score ?? 0);
      this.finished.set(true);
    } else {
      this.question.set(data.next_question);
      this.selected.set('');
    }
  }
}
*/
