import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { TranslocoModule } from '@jsverse/transloco';
import { GameConfigService } from '../../../core/services/game-config.service';

@Component({
  selector: 'app-config',
  standalone: true,
  imports: [ReactiveFormsModule, TranslocoModule],
  templateUrl: './config.component.html'
})
export class ConfigComponent implements OnInit {
  private configSvc = inject(GameConfigService);
  private fb        = inject(FormBuilder);

  saved   = signal(false);
  loading = signal(true);

  form = this.fb.group({
    lives:          [3,  [Validators.required, Validators.min(1), Validators.max(10)]],
    questions:      [16, [Validators.required, Validators.min(5), Validators.max(50)]],
    time_seconds:   [15, [Validators.required, Validators.min(5), Validators.max(120)]],
    points_correct: [10, [Validators.required, Validators.min(1)]],
    points_bonus:   [5,  [Validators.required, Validators.min(0)]],
  });

  ngOnInit(): void {
    this.configSvc.getConfig().subscribe({
      next: res => {
        this.form.patchValue(res.data);
        this.loading.set(false);
      }
    });
  }

  save(): void {
    if (this.form.invalid) return;
    this.configSvc.updateConfig(this.form.value as any).subscribe({
      next: () => {
        this.saved.set(true);
        setTimeout(() => this.saved.set(false), 3000);
      }
    });
  }
}
