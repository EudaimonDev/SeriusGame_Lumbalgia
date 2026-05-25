import { Component, inject, OnInit, signal } from '@angular/core';
import { Router } from '@angular/router';
import { GameService } from '../../../core/services/game.service';
import { Question } from '../../../models/question.model';

@Component({
  selector: 'app-pretest',
  standalone: true,
  templateUrl: './pretest.component.html'
})
export class PretestComponent implements OnInit {
  private gameSvc = inject(GameService);
  private router  = inject(Router);

  sessionId  = signal<number>(0);
  question   = signal<Question | null>(null);
  selected   = signal<string>('');
  answered   = signal<number>(0);
  total      = signal<number>(20);
  loading    = signal<boolean>(true);

  ngOnInit(): void {
    this.gameSvc.startSession('pretest').subscribe({
      next: res => {
        this.sessionId.set(res.data.session_id);
        this.question.set(res.data.question);
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
  0,      // response time (pretest no tiene timer)
  999     // lives_left — en pretest no aplica vidas, ponemos 999 para que nunca termine por vidas
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

    sessionStorage.setItem('feedback', JSON.stringify({
      ...data,
      selected: this.selected(),
      questionText: this.question()?.question_text
    }));

    setTimeout(() => {
      if (data.game_over) {
        this.router.navigate(['/dashboard']);
      } else {
        this.question.set(data.next_question);
        this.selected.set('');
      }
    }, 300);
  }
}
