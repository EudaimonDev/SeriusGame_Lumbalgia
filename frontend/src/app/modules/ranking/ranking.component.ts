import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslocoModule } from '@jsverse/transloco';
import { AuthService } from '../../core/services/auth.service';
import { GameConfigService } from '../../core/services/game-config.service';
import { RankingItem } from '../../models/game-config.model';

@Component({
  selector: 'app-ranking',
  standalone: true,
  imports: [RouterLink, TranslocoModule],
  templateUrl: './ranking.component.html'
})
export class RankingComponent implements OnInit {
  private configSvc = inject(GameConfigService);
  auth              = inject(AuthService);

  ranking    = signal<RankingItem[]>([]);
  myPosition = signal<RankingItem | null>(null);
  loading    = signal(true);
  isDefeat   = signal(false);

  ngOnInit(): void {
    const result = sessionStorage.getItem('gameResult');
    this.isDefeat.set(result === 'defeat');
    sessionStorage.removeItem('gameResult');

    this.configSvc.getRanking().subscribe({
      next: res => {
        this.ranking.set(res.data.ranking);
        this.myPosition.set(res.data.my_position);
        this.loading.set(false);
      }
    });
  }

  getMedalIcon(position: number): string {
    if (position === 1) return '👑';
    if (position === 2) return '🥈';
    if (position === 3) return '🥉';
    return '';
  }

  getPrecisionColor(precision: number): string {
    if (precision >= 80) return 'var(--green)';
    if (precision >= 60) return 'var(--amber)';
    return 'var(--red)';
  }

  getStars(percentage: number): boolean[] {
  const count = percentage === 100 ? 5 :
                percentage >= 80  ? 4 :
                percentage >= 60  ? 3 :
                percentage >= 40  ? 2 : 1;
  return Array(5).fill(false).map((_, i) => i < count);
}
}
