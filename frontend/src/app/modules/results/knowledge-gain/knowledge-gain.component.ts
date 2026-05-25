import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ResultService } from '../../../core/services/result.service';
import { MyResultsResponse } from '../../../models/result.model';

@Component({
  selector: 'app-knowledge-gain',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './knowledge-gain.component.html'
})
export class KnowledgeGainComponent implements OnInit {
  private resultSvc = inject(ResultService);
  data = signal<MyResultsResponse | null>(null);

  ngOnInit(): void {
    this.resultSvc.getMyResults().subscribe({
      next: res => this.data.set(res.data)
    });
  }
}
