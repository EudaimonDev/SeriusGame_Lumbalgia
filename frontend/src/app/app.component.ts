// src/app/app.component.ts
import { Component, inject, computed } from '@angular/core';
import { RouterOutlet, Router } from '@angular/router';
import { NavbarComponent } from './shared/navbar/navbar.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, NavbarComponent],
  template: `
    <app-navbar />
    @if (isFullPage()) {
      <router-outlet />
    } @else {
      <main>
        <router-outlet />
      </main>
    }
  `
})
export class AppComponent {
  private router = inject(Router);

  isFullPage = computed(() => {
    const fullPageRoutes = ['/login', '/admin/login'];
    return fullPageRoutes.some(r => this.router.url.startsWith(r));
  });
}
