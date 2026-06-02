// src/app/modules/profile/profile.component.ts
import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ProfileService } from '../../core/services/profile.service';
import { StudentProfile } from '../../models/user.model';
import { TranslocoModule } from '@jsverse/transloco';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [RouterLink, TranslocoModule],
  templateUrl: './profile.component.html'
})
export class ProfileComponent implements OnInit {
  private profileSvc = inject(ProfileService);
  profile = signal<StudentProfile | null>(null);
  loading = signal(true);

  ngOnInit(): void {
    document.body.classList.add('rpg-theme');
    this.profileSvc.getProfile().subscribe({
      next: res => {
        this.profile.set(res.data);
        this.loading.set(false);
      }
    });
  }

  ngOnDestroy(): void {
    document.body.classList.add('rpg-theme');
  }
}
