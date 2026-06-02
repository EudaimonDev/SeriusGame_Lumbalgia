import { Component, OnInit, signal, inject, effect } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RoomService, Room } from '../../../core/services/room.service';
import { environment } from '../../../../environments/environment';
import { TranslocoModule } from '@jsverse/transloco';
import { LanguageService } from '../../../core/services/language.service';

@Component({
  selector: 'app-rooms',
  standalone: true,
  imports: [ReactiveFormsModule, TranslocoModule],
  templateUrl: './rooms.component.html'
})
export class RoomsComponent implements OnInit {

  rooms      = signal<Room[]>([]);
  showForm   = signal(false);
  loading    = signal(false);
  saved      = signal(false);
  error      = signal('');
  editingId  = signal<number | null>(null);
  categories = signal<any[]>([]);
  form: ReturnType<FormBuilder['group']>;
  selectedCategories = signal<number[]>([]);

  // ← inject fuera del constructor
  private roomSvc = inject(RoomService);
  private fb      = inject(FormBuilder);
  private http    = inject(HttpClient);
  private langSvc = inject(LanguageService);

   constructor() {
    this.form = this.fb.group({
      code:            ['', [Validators.required, Validators.maxLength(20)]],
      name:            ['', Validators.required],
      group_type:      ['experimental', Validators.required],
      //phase:           ['game', Validators.required],
      questions_count: [10, [Validators.required, Validators.min(1), Validators.max(50)]],
      difficulty:      ['adaptive', Validators.required],
      category_ids:    [''],
    });

    // ← effect dentro del constructor, después del form
    effect(() => {
      const lang = this.langSvc.currentLang();
      this.http.get<{ data: any[] }>(
        `${environment.apiUrl}/admin/categories?lang=${lang}`
      ).subscribe({
        next: r => this.categories.set(r.data)
      });
    });
  }

  isCategorySelected(id: number): boolean {
    return this.selectedCategories().includes(id);
  }

  toggleCategory(id: number): void {
    const current = this.selectedCategories();
    if (current.includes(id)) {
      this.selectedCategories.set(current.filter(c => c !== id));
    } else {
      this.selectedCategories.set([...current, id]);
    }
    // Actualizar el form
    const ids = this.selectedCategories();
    this.form.patchValue({
      category_ids: ids.length > 0 ? ids.join(',') : ''
    });
  }

  ngOnInit(): void {
    this.load();
    this.loadCategories();
  }

  loadCategories(): void {
    this.http.get<{ data: any[] }>(
      `${environment.apiUrl}/admin/categories?lang=${this.langSvc.currentLang()}`
    ).subscribe({
      next: r => this.categories.set(r.data)
    });
  }

  onCategorySelect(event: Event): void {
    const select = event.target as HTMLSelectElement;
    const selected = Array.from(select.selectedOptions).map(o => o.value);
    this.form.patchValue({ category_ids: selected.join(',') });
  }

  load(): void {
    this.roomSvc.getRooms().subscribe({
      next: r => this.rooms.set(r.data)
    });
  }

  openCreate(): void {
    this.editingId.set(null);
    this.selectedCategories.set([]);
    this.form.reset({
      group_type:      'experimental',
      //phase:           'game',
      questions_count: 10,
      difficulty:      'adaptive',
      category_ids:    ''
    });
    this.error.set('');
    this.showForm.set(true);
  }

  openEdit(room: Room): void {
    this.editingId.set(room.id);
      const ids = room.category_ids
      ? room.category_ids.split(',').map(Number)
      : [];
    this.selectedCategories.set(ids);
    this.form.patchValue({
      code:            room.code,
      name:            room.name,
      group_type:      room.group_type,
      //phase:           room.phase           ?? 'game',
      questions_count: room.questions_count ?? 10,
      difficulty:      room.difficulty      ?? 'adaptive',
      category_ids:    room.category_ids    ?? '',
    });
    this.error.set('');
    this.showForm.set(true);
  }

  save(): void {
    if (this.form.invalid) return;
    this.loading.set(true);
    this.error.set('');

    const v  = this.form.value;
    const id = this.editingId();

    const payload = {
      name:            v.name!,
      group_type:      v.group_type!,
      //phase:           v.phase as 'pretest' | 'game' | 'posttest',
      questions_count: Number(v.questions_count),
      difficulty:      v.difficulty!,
      category_ids:    v.category_ids || null,
    };

    const req = id
      ? this.roomSvc.updateRoom(id, payload)
      : this.roomSvc.createRoom({ code: v.code!.toUpperCase(), ...payload });

    req.subscribe({
      next: () => {
        this.loading.set(false);
        this.showForm.set(false);
        this.saved.set(true);
        setTimeout(() => this.saved.set(false), 3000);
        this.load();
      },
      error: (e: any) => {
        this.error.set(e.error?.message ?? 'Error al guardar la sala');
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
