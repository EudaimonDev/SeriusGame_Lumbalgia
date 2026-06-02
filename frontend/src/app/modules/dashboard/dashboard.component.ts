import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SlicePipe } from '@angular/common';
import { TranslocoModule } from '@jsverse/transloco';
import { AuthService } from '../../core/services/auth.service';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [RouterLink, SlicePipe, TranslocoModule],
  templateUrl: './dashboard.component.html'
})
export class DashboardComponent implements OnInit {
  auth       = inject(AuthService);
  http       = inject(HttpClient);
  adminStats = signal<any>(null);

  ngOnInit(): void {
    this.http.get<{ data: any }>(`${environment.apiUrl}/admin/stats`).subscribe({
      next: res => this.adminStats.set(res.data)
    });
  }
}
