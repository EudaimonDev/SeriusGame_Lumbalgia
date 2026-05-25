import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, Router } from '@angular/router';
import { TranslocoModule } from '@jsverse/transloco';
import { AuthService } from '../../core/services/auth.service';
import { LanguageService } from '../../core/services/language.service';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, TranslocoModule],
  templateUrl: './navbar.component.html'
})
export class NavbarComponent {
  auth    = inject(AuthService);
  router  = inject(Router);
  langSvc = inject(LanguageService);

  get showNav(): boolean {
    const hiddenRoutes = ['/login', '/admin/login'];
    return !hiddenRoutes.some(r => this.router.url.startsWith(r));
  }
}
