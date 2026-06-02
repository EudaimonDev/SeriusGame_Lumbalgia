import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const phaseGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);

  // Si no tiene sala, puede jugar libremente
  if (!auth.hasRoom()) return true;

  const phase = auth.getRoomPhase();

  // Si la fase es game, puede entrar al juego
  if (phase === 'game') return true;

  // Si la fase es pretest o posttest, redirigir
  if (phase === 'pretest') {
    router.navigate(['/pretest']);
    return false;
  }

  if (phase === 'posttest') {
    router.navigate(['/posttest']);
    return false;
  }

  return true;
};
