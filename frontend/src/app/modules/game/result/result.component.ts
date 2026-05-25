import { Component, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { TranslocoModule } from '@jsverse/transloco';
import { GameService } from '../../../core/services/game.service';

@Component({
  selector: 'app-result',
  standalone: true,
  imports: [RouterLink, TranslocoModule],
  templateUrl: './result.component.html'
})
export class ResultComponent implements OnInit {
  private route   = inject(ActivatedRoute);
  private gameSvc = inject(GameService);

  result    = signal<any>(null);
  sessionId = signal<number>(0);

  ngOnInit(): void {
    this.route.queryParams.subscribe(p => {
      const id = +p['session_id'];
      this.sessionId.set(id);
      this.gameSvc.getResult(id).subscribe({ next: res => this.result.set(res.data) });
    });
  }

  getStars(percentage: number): boolean[] {
    const count = percentage === 100 ? 5 :
                  percentage >= 80  ? 4 :
                  percentage >= 60  ? 3 :
                  percentage >= 40  ? 2 : 1;
    return Array(5).fill(false).map((_, i) => i < count);
  }
}
