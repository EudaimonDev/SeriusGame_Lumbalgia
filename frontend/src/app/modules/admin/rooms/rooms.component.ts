// src/app/modules/admin/rooms/rooms.component.ts
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RoomService, Room } from '../../../core/services/room.service';

@Component({
  selector: 'app-rooms',
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: './rooms.component.html'
})
export class RoomsComponent implements OnInit {
  private roomSvc = inject(RoomService);
  private fb      = inject(FormBuilder);

  rooms    = signal<Room[]>([]);
  showForm = signal(false);
  loading  = signal(false);
  saved    = signal(false);
  error    = signal('');

  form = this.fb.group({
    code:       ['', [Validators.required, Validators.maxLength(20)]],
    name:       ['', Validators.required],
    group_type: ['experimental', Validators.required],
  });

  ngOnInit(): void { this.load(); }

  load(): void {
    this.roomSvc.getRooms().subscribe({
      next: r => this.rooms.set(r.data)
    });
  }

  openCreate(): void {
    this.form.reset({ group_type: 'experimental' });
    this.error.set('');
    this.showForm.set(true);
  }

  save(): void {
    if (this.form.invalid) return;
    this.loading.set(true);
    this.error.set('');

    const v = this.form.value;
    this.roomSvc.createRoom({
      code:       v.code!.toUpperCase(),
      name:       v.name!,
      group_type: v.group_type!
    }).subscribe({
      next: () => {
        this.loading.set(false);
        this.showForm.set(false);
        this.saved.set(true);
        setTimeout(() => this.saved.set(false), 3000);
        this.load();
      },
      error: (e: any) => {
        this.error.set(e.error?.message ?? 'Error al crear la sala');
        this.loading.set(false);
      }
    });
  }

  toggle(id: number): void {
    this.roomSvc.toggleRoom(id).subscribe({
      next: res => {
        this.rooms.update(rs =>
          rs.map(r => r.id === id ? { ...r, is_active: res.data.is_active } : r)
        );
      }
    });
  }

  delete(id: number): void {
    if (!confirm('¿Eliminar esta sala? Los estudiantes vinculados perderán la referencia.')) return;
    this.roomSvc.deleteRoom(id).subscribe({
      next: () => this.load()
    });
  }

  generateCode(): void {
    const prefix = this.form.value.group_type === 'control' ? 'CTRL' : 'EXP';
    const year   = new Date().getFullYear();
    const rand   = Math.floor(Math.random() * 900) + 100;
    this.form.patchValue({ code: `${prefix}-${year}-${rand}` });
  }

  copyCode(code: string): void {
    navigator.clipboard.writeText(code);
  }
}
