// src/app/modules/auth/admin-login/admin-login.component.ts
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-admin-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './admin-login.component.html'
})
export class AdminLoginComponent {
  private fb     = inject(FormBuilder);
  private auth   = inject(AuthService);
  private router = inject(Router);

  form = this.fb.group({
    email:    ['', [Validators.required, Validators.email]],
    password: ['', Validators.required]
  });

  forgotForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]]
  });

  error         = '';
  loading       = false;
  showForgot    = signal(false);
  forgotLoading = false;
  forgotMsg     = '';
  forgotError   = '';

  submit(): void {
  if (this.form.invalid) return;
  this.loading = true;
  this.error   = '';
  const { email, password } = this.form.value;
  this.auth.adminLogin(email!, password!).subscribe({
    next: () => this.router.navigate(['/dashboard']),
    error: (e: any) => {
      this.error   = e.error?.message ?? 'Credenciales incorrectas';
      this.loading = false;
    }
  });
}

  submitForgot(): void {
    if (this.forgotForm.invalid) return;
    this.forgotLoading = true;
    this.forgotMsg     = '';
    this.forgotError   = '';
    this.auth.forgotPassword(this.forgotForm.value.email!).subscribe({
      next: (res: any) => {
        this.forgotMsg     = res.message;
        this.forgotLoading = false;
      },
      error: (e) => {
        this.forgotError   = e.error?.message ?? 'Error al enviar el correo';
        this.forgotLoading = false;
      }
    });
  }

  ngOnInit(): void {
  document.body.style.overflow = 'hidden';
}

ngOnDestroy(): void {
  document.body.style.overflow = '';
}
}
