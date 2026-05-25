// src/app/modules/results/my-results/my-results.component.ts
import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ResultService } from '../../../core/services/result.service';
import { MyResultsResponse } from '../../../models/result.model';

@Component({
  selector: 'app-my-results',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './my-results.component.html'
})
export class MyResultsComponent implements OnInit {
  private resultSvc = inject(ResultService);
  data = signal<MyResultsResponse | null>(null);

  ngOnInit(): void {
    this.resultSvc.getMyResults().subscribe({ next: res => this.data.set(res.data) });
  }
}
