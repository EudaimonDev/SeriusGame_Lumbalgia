// src/app/modules/admin/reports/reports.component.ts
import { Component, inject, OnInit, signal } from '@angular/core';
import { ResultService } from '../../../core/services/result.service';
import { GroupReport } from '../../../models/result.model';

@Component({
  selector: 'app-reports',
  standalone: true,
  templateUrl: './reports.component.html'
})
export class ReportsComponent implements OnInit {
  private resultSvc = inject(ResultService);
  report = signal<GroupReport | null>(null);

  ngOnInit(): void {
    this.resultSvc.getGroupReport().subscribe({ next: r => this.report.set(r.data) });
  }
}
