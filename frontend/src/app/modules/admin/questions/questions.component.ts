// src/app/modules/admin/questions/questions.component.ts
import { Component, inject, OnInit, signal, computed, effect} from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { AdminService } from '../../../core/services/admin.service';
import { AdminQuestion } from '../../../models/question.model';
import { TranslocoModule } from '@jsverse/transloco';
import { JsonPipe } from '@angular/common';
import { LanguageService } from '../../../core/services/language.service';

@Component({
  selector: 'app-questions',
  standalone: true,
  imports: [ReactiveFormsModule, TranslocoModule, JsonPipe],
  templateUrl: './questions.component.html'
})
export class QuestionsComponent implements OnInit {
  private adminSvc = inject(AdminService);
  private fb       = inject(FormBuilder);
  private langSvc = inject(LanguageService);
  currentPage  = signal(1);
  pageSize     = signal(15);

  questions      = signal<AdminQuestion[]>([]);
  categories     = signal<any[]>([]);
  showForm       = signal(false);
  showImport     = signal(false);
  showGenerate   = signal(false);
  editingId      = signal<number | null>(null);
  importing      = signal(false);
  generating     = signal(false);
  importResult   = signal<any>(null);
  generateResult = signal<any>(null);
  selectedFile   = signal<File | null>(null);
  filterDifficulty = signal<string>('');
  filterCategory   = signal<string>('');
  sortBy           = signal<string>('id');
  showCategories  = signal(false);
  editingCatId    = signal<number | null>(null);
  catError        = signal('');

  catForm = this.fb.group({
    name:        ['', Validators.required],
    description: ['']
  });

  constructor() {
  effect(() => {
    this.adminSvc.getQuestions({ language: this.langSvc.currentLang() }).subscribe({
      next: r => this.questions.set(r.data)
    });
  });
  effect(() => {
  this.filterDifficulty();
  this.filterCategory();
  this.sortBy();
  this.currentPage.set(1);
});
}

pagedQuestions = computed(() => {
  const all   = this.filteredQuestions();
  const start = (this.currentPage() - 1) * this.pageSize();
  return all.slice(start, start + this.pageSize());
});

totalPages = computed(() =>
  Math.ceil(this.filteredQuestions().length / this.pageSize())
);

pages = computed(() =>
  Array.from({ length: this.totalPages() }, (_, i) => i + 1)
);

min(a: number, b: number): number {
  return Math.min(a, b);
}
  //METODOS PARA CATEGORIAS
  openCategories(): void {
    this.showCategories.update(v => !v);
    this.showForm.set(false);
    this.showImport.set(false);
    this.showGenerate.set(false);
    this.catError.set('');
    this.catForm.reset();
    this.editingCatId.set(null);
  }

  openEditCat(cat: any): void {
    this.catForm.patchValue({ name: cat.name, description: cat.description ?? '' });
    this.editingCatId.set(cat.id);
    this.catError.set('');
  }

  saveCat(): void {
    if (this.catForm.invalid) return;
    const v   = this.catForm.value;
    const id  = this.editingCatId();
    const req = id
      ? this.adminSvc.updateCategory(id, { name: v.name!, description: v.description ?? '' })
      : this.adminSvc.createCategory({ name: v.name!, description: v.description ?? '' });

    req.subscribe({
      next: () => {
        this.catForm.reset();
        this.editingCatId.set(null);
        this.catError.set('');
        this.adminSvc.getCategories().subscribe({ next: r => this.categories.set(r.data) });
      },
      error: (e: any) => this.catError.set(e.error?.message ?? 'Error al guardar')
    });
  }

  deleteCat(id: number, name: string): void {
    if (!confirm(`¿Eliminar la categoría "${name}"?`)) return;
    this.adminSvc.deleteCategory(id).subscribe({
      next: () => {
        this.adminSvc.getCategories().subscribe({ next: r => this.categories.set(r.data) });
      },
      error: (e: any) => alert(e.error?.message ?? 'Error al eliminar')
    });
  }

  cancelCat(): void {
    this.catForm.reset();
    this.editingCatId.set(null);
    this.catError.set('');
  }
  //-------------------

  form = this.fb.group({
    category_id:    ['', Validators.required],
    difficulty:     ['easy', Validators.required],
    question_text:  ['', Validators.required],
    option_a:       ['', Validators.required],
    option_b:       ['', Validators.required],
    option_c:       ['', Validators.required],
    option_d:       ['', Validators.required],
    correct_answer: ['a', Validators.required],
    feedback_text:  ['']
  });

  generateForm = this.fb.group({
    category_id:   ['', Validators.required],
    category_name: ['', Validators.required],
    difficulty:    ['easy', Validators.required],
    language:      ['español', Validators.required],
    count:         [5, [Validators.required, Validators.min(1), Validators.max(10)]]
  });

  ngOnInit(): void {
    this.load();
    this.loadCategories();

  }

  sortAsc = signal<boolean>(true);

  loadCategories(): void {
    this.adminSvc.getCategories(this.langSvc.currentLang()).subscribe({
      next: r => this.categories.set(r.data)
    });
  }

  toggleSortDir(): void {
    this.sortAsc.update(v => !v);
  }

  load(): void {
    this.adminSvc.getQuestions({ language: this.langSvc.currentLang() }).subscribe({
      next: r => this.questions.set(r.data)
    });
  }

  filteredQuestions = computed(() => {
    let qs = this.questions();

    if (this.filterDifficulty()) {
      qs = qs.filter(q => q.difficulty === this.filterDifficulty());
    }

    if (this.filterCategory()) {
      qs = qs.filter(q => String(q.category_id) === this.filterCategory());
    }

    const sort = this.sortBy();
    qs = [...qs].sort((a, b) => {
      let result = 0;
      if (sort === 'id')         result = a.id - b.id;
      if (sort === 'difficulty') {
        const order = { easy: 0, medium: 1, hard: 2 };
        result = (order[a.difficulty as keyof typeof order] ?? 0) -
                (order[b.difficulty as keyof typeof order] ?? 0);
      }
      if (sort === 'category') {
        result = (a.category_name ?? '').localeCompare(b.category_name ?? '');
      }
      return this.sortAsc() ? result : -result;
    });

    return qs;
  });

  // ── CRUD manual ──────────────────────────────────────────

  openCreate(): void {
    this.form.reset({ difficulty: 'easy', correct_answer: 'a' });
    this.editingId.set(null);
    this.showForm.set(true);
    this.showImport.set(false);
    this.showGenerate.set(false);
  }

  openEdit(q: AdminQuestion): void {
    this.form.patchValue({
      category_id:    String(q.category_id),
      difficulty:     q.difficulty,
      question_text:  q.question_text,
      option_a:       q.option_a,
      option_b:       q.option_b,
      option_c:       q.option_c,
      option_d:       q.option_d,
      correct_answer: q.correct_answer,
      feedback_text:  q.feedback_text,
    });
    this.editingId.set(q.id);
    this.showForm.set(true);
    this.showImport.set(false);
    this.showGenerate.set(false);
  }

  save(): void {
    if (this.form.invalid) return;
    const id  = this.editingId();
    const req = id
      ? this.adminSvc.updateQuestion(id, this.form.value)
      : this.adminSvc.createQuestion(this.form.value);
    req.subscribe({ next: () => { this.load(); this.showForm.set(false); } });
  }

  delete(id: number): void {
    if (!confirm('¿Eliminar esta pregunta?')) return;
    this.adminSvc.deleteQuestion(id).subscribe({ next: () => this.load() });
  }

  // ── Toggle verificar/desverificar ─────────────────────────

  toggle(id: number): void {
    this.adminSvc.toggleQuestion(id).subscribe({
      next: res => {
        this.questions.update(qs =>
          qs.map(q => q.id === id
            ? { ...q, is_active: res.data.is_active }
            : q
          )
        );
      }
    });
  }

  // ── Importar CSV/TXT ──────────────────────────────────────

  openImport(): void {
    this.showImport.set(true);
    this.showForm.set(false);
    this.showGenerate.set(false);
    this.importResult.set(null);
    this.selectedFile.set(null);
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files?.length) {
      this.selectedFile.set(input.files[0]);
    }
  }

  importFile(): void {
    const file = this.selectedFile();
    if (!file) return;
    this.importing.set(true);
    this.adminSvc.importQuestions(file).subscribe({
      next: res => {
        this.importResult.set(res.data);
        this.importing.set(false);
        this.load();
      },
      error: () => this.importing.set(false)
    });
  }

  // ── Generar con IA ────────────────────────────────────────

  openGenerate(): void {
    this.showGenerate.set(true);
    this.showForm.set(false);
    this.showImport.set(false);
    this.generateResult.set(null);
  }

  onCategoryChange(): void {
    const id  = this.generateForm.value.category_id;
    const cat = this.categories().find(c => String(c.id) === String(id));
    if (cat) this.generateForm.patchValue({ category_name: cat.name });
  }

  generate(): void {
    if (this.generateForm.invalid) return;
    this.generating.set(true);
    const v = this.generateForm.value;
    this.adminSvc.generateQuestions({
      category_id:   Number(v.category_id),
      category_name: v.category_name!,
      difficulty:    v.difficulty!,
      count:         Number(v.count),
      language:      v.language!
    }).subscribe({
      next: res => {
        this.generateResult.set(res.data);
        this.generating.set(false);
        this.load();
      },
      error: () => this.generating.set(false)
    });
  }

  // ── Helper ────────────────────────────────────────────────

  getOption(q: AdminQuestion, opt: string): string {
    const key = `option_${opt}` as keyof AdminQuestion;
    return q[key] as string;
  }

  difficultyLabel(d: string): string {
    return d === 'easy' ? 'Fácil' : d === 'medium' ? 'Medio' : 'Difícil';
  }
}
