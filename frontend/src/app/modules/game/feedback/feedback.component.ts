// src/app/modules/game/feedback/feedback.component.ts
import { Component, OnInit, signal, inject } from '@angular/core';
import { Router } from '@angular/router';
import { AnswerResponse } from '../../../models/session.model';
import { TranslocoModule } from '@jsverse/transloco';

@Component({
  selector: 'app-feedback',
  standalone: true,
  imports: [TranslocoModule],
  templateUrl: './feedback.component.html'
})
export class FeedbackComponent implements OnInit {
  private router = inject(Router);
  data = signal<(AnswerResponse & { selected: string; questionText: string }) | null>(null);

  ngOnInit(): void {
    const raw = sessionStorage.getItem('feedback');
    if (!raw) { this.router.navigate(['/game']); return; }
    this.data.set(JSON.parse(raw));
  }

  continue(): void {
    const d = this.data();
    if (d?.game_over) {
      this.router.navigate(['/result']);
    } else {
      // Volver al juego con la siguiente pregunta ya guardada en el servicio
      this.router.navigate(['/game']);
    }
  }
}
