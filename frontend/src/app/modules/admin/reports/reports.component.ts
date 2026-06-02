import { Component, inject, OnInit, signal, ElementRef, ViewChild, effect, computed } from '@angular/core';
import { Chart, registerables } from 'chart.js';
import { AdminService } from '../../../core/services/admin.service';
import { SessionEvolution, StudentStat, TestComparison } from '../../../models/result.model';
import { TranslocoModule } from '@jsverse/transloco';
Chart.register(...registerables);

@Component({
  selector: 'app-reports',
  standalone: true,
  imports: [TranslocoModule],
  templateUrl: './reports.component.html'
})
export class ReportsComponent implements OnInit {
  private adminSvc = inject(AdminService);
  //@ViewChild('comparisonCanvas') comparisonCanvas!: ElementRef<HTMLCanvasElement>;

  activeTab = signal<'rooms' | 'students' | 'charts' | 'questions'>('rooms'); //'comparison' |
  rooms        = signal<any[]>([]);
  students     = signal<any[]>([]);
  evolution    = signal<SessionEvolution[]>([]);
  stats        = signal<StudentStat[]>([]);
  selectedRoom = signal<number | null>(null);
  loading      = signal(true);
  //testComparison = signal<TestComparison[]>([]);
  questions    = signal<any[]>([]);
  sortQBy      = signal<'total' | 'success' | 'fail' | 'time'>('total');
  sortQAsc     = signal(false);

  @ViewChild('precisionCanvas')  precisionCanvas!:  ElementRef<HTMLCanvasElement>;
  @ViewChild('responsesCanvas')  responsesCanvas!:  ElementRef<HTMLCanvasElement>;
  @ViewChild('evolutionCanvas')  evolutionCanvas!:  ElementRef<HTMLCanvasElement>;
  @ViewChild('statsCanvas')      statsCanvas!:      ElementRef<HTMLCanvasElement>;

  private charts: Record<string, any> = {};

  constructor() {
    effect(() => {
      if (this.activeTab() === 'charts') {
        const hasData = this.rooms().length > 0 && this.stats().length > 0;
        if (hasData) setTimeout(() => this.buildAllCharts(), 0);
      }
      /*if (this.activeTab() === 'comparison') {
        setTimeout(() => this.buildComparisonChart(), 0);
      }*/
    });
  }

  filteredQuestions = computed(() => {
    const qs   = this.questions();
    const sort = this.sortQBy();
    return [...qs].sort((a, b) => {
      let r = 0;
      if (sort === 'total')   r = a.total_answers - b.total_answers;
      if (sort === 'success') r = a.success_rate  - b.success_rate;
      if (sort === 'fail')    r = (100 - a.success_rate) - (100 - b.success_rate);
      if (sort === 'time')    r = a.avg_time_sec  - b.avg_time_sec;
      return this.sortQAsc() ? r : -r;
    });
  });

  getMostSelectedOption(row: any): string {
    const opts = ['a','b','c','d'];
    const counts = { a: +row.count_a, b: +row.count_b, c: +row.count_c, d: +row.count_d };
    return opts.reduce((max, o) => counts[o as keyof typeof counts] > counts[max as keyof typeof counts] ? o : max, 'a');
  }

  /*buildComparisonChart(): void {
    this.destroyChart('comparison');
    if (!this.comparisonCanvas) return;

    const isDark  = matchMedia('(prefers-color-scheme: dark)').matches;
    const gridClr = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textClr = isDark ? '#aaa' : '#666';

    const exp  = this.getTestComparisonByGroup('experimental');
    const ctrl = this.getTestComparisonByGroup('control');

    this.charts['comparison'] = new Chart(this.comparisonCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels: ['Experimental', 'Control'],
        datasets: [
          {
            label: 'Pretest',
            data: [exp.avgPretest ?? 0, ctrl.avgPretest ?? 0],
            backgroundColor: '#378ADDCC',
            borderColor: '#378ADD',
            borderWidth: 1.5,
            borderRadius: 6,
          },
          {
            label: 'Posttest',
            data: [exp.avgPosttest ?? 0, ctrl.avgPosttest ?? 0],
            backgroundColor: '#1D9E75CC',
            borderColor: '#1D9E75',
            borderWidth: 1.5,
            borderRadius: 6,
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { ticks: { color: textClr }, grid: { color: gridClr }, beginAtZero: true },
          x: { ticks: { color: textClr }, grid: { display: false } }
        }
      }
    });
  }*/

  ngOnInit(): void {
    this.loadRooms();
    this.loadStudents();
    this.loadChartData();
    //this.loadTestComparison();
    this.adminSvc.getReportQuestions().subscribe({
      next: r => this.questions.set(r.data)
    });
  }

  /*loadTestComparison(): void {
    this.adminSvc.getReportTestComparison().subscribe({
      next: r => this.testComparison.set(r.data)
    });
  }*/

  loadRooms(): void {
    this.adminSvc.getReportRooms().subscribe({
      next: r => { this.rooms.set(r.data); this.loading.set(false); }
    });
  }

  loadStudents(roomId?: number): void {
    this.adminSvc.getReportStudents(roomId).subscribe({
      next: r => this.students.set(r.data)
    });
  }

  loadChartData(): void {
    this.adminSvc.getReportEvolution().subscribe({
      next: r => this.evolution.set(r.data)
    });
    this.adminSvc.getReportStats().subscribe({
      next: r => this.stats.set(r.data)
    });
  }

  filterByRoom(roomId: number | null): void {
    this.selectedRoom.set(roomId);
    this.loadStudents(roomId ?? undefined);
    this.activeTab.set('students');
  }

  getPrecisionColor(p: number): string {
    if (p >= 80) return 'var(--green)';
    if (p >= 60) return 'var(--amber)';
    return 'var(--red)';
  }

  // ── Estadísticas ──────────────────────────────────────────

  private median(arr: number[]): number {
    if (!arr.length) return 0;
    const sorted = [...arr].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 !== 0
      ? sorted[mid]
      : (sorted[mid - 1] + sorted[mid]) / 2;
  }

  private stdDev(arr: number[]): number {
    if (arr.length < 2) return 0;
    const mean = arr.reduce((a, b) => a + b, 0) / arr.length;
    const variance = arr.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / arr.length;
    return Math.sqrt(variance);
  }

  getGroupStats(group: 'experimental' | 'control') {
    const data = this.stats().filter(s => s.group_type === group);
    const allScores = data.flatMap(s => s.scores);
    const precisions = data.map(s => +s.avg_precision); // ← el + castea a número
    return {
      count:   data.length,
      mean:    allScores.length ? +(allScores.reduce((a,b) => a+b,0) / allScores.length).toFixed(1) : 0,
      median:  +this.median(allScores).toFixed(1),
      stdDev:  +this.stdDev(allScores).toFixed(1),
      avgPrec: precisions.length ? +(precisions.reduce((a,b) => a+b,0) / precisions.length).toFixed(1) : 0,
    };
  }

  // ── Charts ────────────────────────────────────────────────

  private destroyChart(key: string): void {
    this.charts[key]?.destroy();
    delete this.charts[key];
  }

  buildAllCharts(): void {
    this.buildPrecisionChart();
    this.buildResponsesChart();
    this.buildEvolutionChart();
    this.buildStatsChart();
  }

  buildPrecisionChart(): void {
    this.destroyChart('precision');
    const rooms = this.rooms();
    if (!rooms.length || !this.precisionCanvas) return;

    const isDark  = matchMedia('(prefers-color-scheme: dark)').matches;
    const gridClr = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textClr = isDark ? '#aaa' : '#666';

    this.charts['precision'] = new Chart(this.precisionCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels: rooms.map(r => r.name),
        datasets: [{
          label: 'Precisión (%)',
          data: rooms.map(r => r.avg_precision ?? 0),
          backgroundColor: rooms.map(r =>
            (r.group_type === 'experimental' ? '#1D9E75' : '#E24B4A') + 'CC'),
          borderColor: rooms.map(r =>
            r.group_type === 'experimental' ? '#1D9E75' : '#E24B4A'),
          borderWidth: 1.5,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (ctx: any) => ` ${ctx.parsed.y}%` } }
        },
        scales: {
          y: {
            min: 0, max: 100,
            ticks: { color: textClr, callback: (v: string | number) => typeof v === 'number' ? v + '%' : v },
            grid: { color: gridClr }
          },
          x: { ticks: { color: textClr }, grid: { display: false } }
        }
      }
    });
  }

  buildResponsesChart(): void {
    this.destroyChart('responses');
    const rooms = this.rooms();
    if (!rooms.length || !this.responsesCanvas) return;

    const isDark  = matchMedia('(prefers-color-scheme: dark)').matches;
    const gridClr = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textClr = isDark ? '#aaa' : '#666';

    const correctas   = rooms.map(r => Math.round((r.total_sessions ?? 0) * ((r.avg_precision ?? 0) / 100) * 5));
    const incorrectas = rooms.map(r => Math.round((r.total_sessions ?? 0) * ((100 - (r.avg_precision ?? 0)) / 100) * 5));

    this.charts['responses'] = new Chart(this.responsesCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels: rooms.map(r => r.name),
        datasets: [
          { label: 'Correctas',   data: correctas,   backgroundColor: '#1D9E75CC', borderColor: '#1D9E75', borderWidth: 1.5, borderRadius: 6 },
          { label: 'Incorrectas', data: incorrectas, backgroundColor: '#378ADDCC', borderColor: '#378ADD', borderWidth: 1.5, borderRadius: 6 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { ticks: { color: textClr }, grid: { color: gridClr } },
          x: { ticks: { color: textClr }, grid: { display: false } }
        }
      }
    });
  }

  buildEvolutionChart(): void {
    this.destroyChart('evolution');
    const data = this.evolution();
    if (!data.length || !this.evolutionCanvas) return;

    const isDark  = matchMedia('(prefers-color-scheme: dark)').matches;
    const gridClr = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textClr = isDark ? '#aaa' : '#666';

    // Promedio de precisión por número de sesión, separado por grupo
    const expData  = this.avgBySession(data.filter(d => d.group_type === 'experimental'));
    const ctrlData = this.avgBySession(data.filter(d => d.group_type === 'control'));
    const maxSession = Math.max(expData.length, ctrlData.length);
    const labels = Array.from({ length: maxSession }, (_, i) => `Sesión ${i + 1}`);

    this.charts['evolution'] = new Chart(this.evolutionCanvas.nativeElement, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Experimental',
            data: expData,
            borderColor: '#1D9E75',
            backgroundColor: '#1D9E7520',
            borderWidth: 2,
            pointRadius: 4,
            tension: 0.3,
            fill: true,
          },
          {
            label: 'Control',
            data: ctrlData,
            borderColor: '#E24B4A',
            backgroundColor: '#E24B4A20',
            borderWidth: 2,
            pointRadius: 4,
            tension: 0.3,
            fill: true,
            borderDash: [5, 4],
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            min: 0, max: 100,
            ticks: { color: textClr, callback: (v: string | number) => typeof v === 'number' ? v + '%' : v },
            grid: { color: gridClr }
          },
          x: { ticks: { color: textClr }, grid: { display: false } }
        }
      }
    });
  }

  buildStatsChart(): void {
    this.destroyChart('stats');
    if (!this.statsCanvas) return;

    const isDark  = matchMedia('(prefers-color-scheme: dark)').matches;
    const gridClr = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textClr = isDark ? '#aaa' : '#666';

    const exp  = this.getGroupStats('experimental');
    const ctrl = this.getGroupStats('control');

    this.charts['stats'] = new Chart(this.statsCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels: ['Media', 'Mediana', 'Desv. Estándar', 'Prec. Promedio (%)'],
        datasets: [
          {
            label: 'Experimental',
            data: [exp.mean, exp.median, exp.stdDev, exp.avgPrec],
            backgroundColor: '#1D9E75CC',
            borderColor: '#1D9E75',
            borderWidth: 1.5,
            borderRadius: 6,
          },
          {
            label: 'Control',
            data: [ctrl.mean, ctrl.median, ctrl.stdDev, ctrl.avgPrec],
            backgroundColor: '#E24B4ACC',
            borderColor: '#E24B4A',
            borderWidth: 1.5,
            borderRadius: 6,
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { ticks: { color: textClr }, grid: { color: gridClr } },
          x: { ticks: { color: textClr }, grid: { display: false } }
        }
      }
    });
  }

  private avgBySession(data: SessionEvolution[]): number[] {
    const bySession: Record<number, number[]> = {};
    data.forEach(d => {
      const n = d.session_number;
      if (!bySession[n]) bySession[n] = [];
      bySession[n].push(d.precision);
    });
    const maxN = Math.max(...Object.keys(bySession).map(Number));
    return Array.from({ length: maxN }, (_, i) => {
      const arr = bySession[i + 1] ?? [];
      return arr.length ? +( arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1) : 0;
    });
  }

  /*getTestComparisonByGroup(group: 'experimental' | 'control') {
    const data = this.testComparison().filter(s => s.group_type === group);
    const withPretest  = data.filter(s => s.pretest_score !== null);
    const withPosttest = data.filter(s => s.posttest_score !== null);
    const withBoth     = data.filter(s => s.pretest_score !== null && s.posttest_score !== null);

    const avg = (arr: number[]) => arr.length
      ? +(arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1)
      : null;

    return {
      avgPretest:  avg(withPretest.map(s => +s.pretest_score!)),
      avgPosttest: avg(withPosttest.map(s => +s.posttest_score!)),
      avgGain:     avg(withBoth.map(s => +(s.posttest_score! - s.pretest_score!).toFixed(1))),
      count:       data.length,
      completedBoth: withBoth.length,
    };
  }*/
}
