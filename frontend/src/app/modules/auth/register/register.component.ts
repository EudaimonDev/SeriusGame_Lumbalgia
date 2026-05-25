// src/app/modules/auth/register/register.component.ts
import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [],
  template: ''
})
export class RegisterComponent {
  constructor() {
    inject(Router).navigate(['/login']);
  }
}
