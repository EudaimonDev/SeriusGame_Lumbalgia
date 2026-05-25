import { Injectable, signal } from '@angular/core';
import { TranslocoService } from '@jsverse/transloco';

@Injectable({ providedIn: 'root' })
export class LanguageService {
  private _lang = signal<'es' | 'en'>('es');
  currentLang   = this._lang.asReadonly();

  constructor(private transloco: TranslocoService) {
    const saved = localStorage.getItem('lang') as 'es' | 'en';
    if (saved) {
      this._lang.set(saved);
      this.transloco.setActiveLang(saved);
    }
  }

  toggle(): void {
    const next = this._lang() === 'es' ? 'en' : 'es';
    this._lang.set(next);
    this.transloco.setActiveLang(next);
    localStorage.setItem('lang', next);
  }

  setLang(lang: 'es' | 'en'): void {
    this._lang.set(lang);
    this.transloco.setActiveLang(lang);
    localStorage.setItem('lang', lang);
  }
}
