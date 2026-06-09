import { Component, OnInit, signal, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { SlicePipe } from '@angular/common';
import { environment } from '../../../../environments/environment';
import { TranslocoModule } from '@jsverse/transloco';

interface Admin {
  id: number;
  name: string;
  email: string;
  role: string;
  created_at: string;
}

@Component({
  selector: 'app-admins',
  standalone: true,
  imports: [ReactiveFormsModule, TranslocoModule, SlicePipe],
  templateUrl: './admins.component.html'
})
export class AdminsComponent implements OnInit {

  admins    = signal<Admin[]>([]);
  showForm  = signal(false);
  loading   = signal(false);
  saved     = signal(false);
  error     = signal('');
  editingId = signal<number | null>(null);

  private http = inject(HttpClient);
  private fb   = inject(FormBuilder);

  form: ReturnType<FormBuilder['group']>;

  constructor() {
    this.form = this.fb.group({
      name:     ['', Validators.required],
      email:    ['', [Validators.required, Validators.email]],
      password: [''],
    });
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.http.get<{ data: Admin[] }>(`${environment.apiUrl}/admin/admins`).subscribe({
      next: r => this.admins.set(r.data)
    });
  }

  openCreate(): void {
    this.editingId.set(null);
    this.form.reset();
    this.form.get('password')?.setValidators([Validators.required, Validators.minLength(6)]);
    this.form.get('password')?.updateValueAndValidity();
    this.error.set('');
    this.showForm.set(true);
  }

  openEdit(admin: Admin): void {
    this.editingId.set(admin.id);
    this.form.patchValue({ name: admin.name, email: admin.email, password: '' });
    this.form.get('password')?.clearValidators();
    this.form.get('password')?.updateValueAndValidity();
    this.error.set('');
    this.showForm.set(true);
  }

  save(): void {
    if (this.form.invalid) return;
    this.loading.set(true);
    this.error.set('');

    const v  = this.form.value;
    const id = this.editingId();

    const payload: any = { name: v.name, email: v.email };
    if (v.password) payload.password = v.password;

    const req = id
      ? this.http.put(`${environment.apiUrl}/admin/admins/${id}`, payload)
      : this.http.post(`${environment.apiUrl}/admin/admins`, payload);

    req.subscribe({
      next: () => {
        this.loading.set(false);
        this.showForm.set(false);
        this.saved.set(true);
        setTimeout(() => this.saved.set(false), 3000);
        this.load();
      },
      error: (e: any) => {
        this.error.set(e.error?.message ?? 'Error al guardar');
        this.loading.set(false);
      }
    });
  }

  delete(id: number): void {
    if (!confirm('¿Eliminar este administrador?')) return;
    this.http.delete(`${environment.apiUrl}/admin/admins/${id}`).subscribe({
      next: () => this.load(),
      error: (e: any) => alert(e.error?.message ?? 'Error al eliminar')
    });
  }
}
