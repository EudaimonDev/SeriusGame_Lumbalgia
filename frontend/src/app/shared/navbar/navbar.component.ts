import { Component, inject, signal, HostListener, ViewEncapsulation  } from '@angular/core';
import { RouterLink, RouterLinkActive, Router } from '@angular/router';
import { TranslocoModule } from '@jsverse/transloco';
import { AuthService } from '../../core/services/auth.service';
import { LanguageService } from '../../core/services/language.service';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, TranslocoModule],
  templateUrl: './navbar.component.html',
  styleUrls: ['../../../styles.scss'],      // ← agrega esto
  encapsulation: ViewEncapsulation.None
})
export class NavbarComponent {
  auth    = inject(AuthService);
  router  = inject(Router);
  langSvc = inject(LanguageService);

  showDropdown = signal(false);

  get showNav(): boolean {
    const hiddenRoutes = ['/login', '/admin/login'];
    return !hiddenRoutes.some(r => this.router.url.startsWith(r));
  }

  get inTestMode(): boolean {
    return !!sessionStorage.getItem('testMode');
  }

  toggleDropdown(): void {
    this.showDropdown.update(v => !v);
  }

  // Cierra el dropdown al hacer click fuera
  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    const target = event.target as HTMLElement;
    if (!target.closest('.nav-avatar-wrap')) {
      this.showDropdown.set(false);
    }
  }
  //CAMBIAR IDIOMA DESDE EL NAVBAR, CON CONFIRMACIÓN SI ESTAMOS EN MODO JUEGO
  changeLanguage(): void {
  if (this.router.url.startsWith('/game')) {
    const msg = this.langSvc.currentLang() === 'es'
      ? '¿Cambiar idioma? La partida actual se perderá.'
      : 'Change language? The current game will be lost.';

    if (!window.confirm(msg)) return;

    // Limpiar sesión de juego
    sessionStorage.removeItem('gameState');
    sessionStorage.removeItem('feedback');
    sessionStorage.removeItem('gameResult');
    sessionStorage.removeItem('defeatStats');

    // Cambiar idioma PRIMERO
    this.langSvc.toggle();
    this.showDropdown.set(false);

    // Forzar destrucción y recreación del componente
    this.router.navigate(['/login']).then(() => {
      this.router.navigate(['/game']);
    });

  } else {
    this.langSvc.toggle();
    this.showDropdown.set(false);
  }
}
}
