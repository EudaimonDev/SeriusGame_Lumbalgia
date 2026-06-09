// src/app/app.routes.ts
import { Routes } from '@angular/router';
import { authGuard }  from './core/guards/auth.guard';
import { adminGuard } from './core/guards/admin.guard';
import { phaseGuard } from './core/guards/phase.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },

  // ── Públicas ─────────────────────────────────────────
  {
    path: 'login',
    loadComponent: () => import('./modules/auth/login/login.component')
      .then(m => m.LoginComponent)
  },
  {
    path: 'admin/login',
    loadComponent: () => import('./modules/auth/admin-login/admin-login.component')
      .then(m => m.AdminLoginComponent)
  },

  // ── Dashboard — solo admin ────────────────────────────
  {
    path: 'dashboard',
    canActivate: [authGuard, adminGuard],
    loadComponent: () => import('./modules/dashboard/dashboard.component')
      .then(m => m.DashboardComponent)
  },

  // ── Perfil — solo estudiante ──────────────────────────
  {
    path: 'profile',
    canActivate: [authGuard],
    loadComponent: () => import('./modules/profile/profile.component')
      .then(m => m.ProfileComponent)
  },

  // ── Juego — estudiante ────────────────────────────────
/*{
  path: 'pretest',
  canActivate: [authGuard], // ← solo authGuard, sin phaseGuard
  loadComponent: () => import('./modules/game/pretest/pretest.component')
    .then(m => m.PretestComponent)
},
{
  path: 'posttest',
  canActivate: [authGuard], // ← solo authGuard, sin phaseGuard
  loadComponent: () => import('./modules/game/posttest/posttest.component')
    .then(m => m.PosttestComponent)
},*/
{
  path: 'game',
  canActivate: [authGuard, phaseGuard], // ← phaseGuard solo aquí
  loadComponent: () => import('./modules/game/play/play.component')
    .then(m => m.PlayComponent)
},
  {
    path: 'feedback',
    canActivate: [authGuard],
    loadComponent: () => import('./modules/game/feedback/feedback.component')
      .then(m => m.FeedbackComponent)
  },
  {
    path: 'result',
    canActivate: [authGuard],
    loadComponent: () => import('./modules/game/result/result.component')
      .then(m => m.ResultComponent)
  },
  {
    path: 'results',
    canActivate: [authGuard],
    loadComponent: () => import('./modules/results/my-results/my-results.component')
      .then(m => m.MyResultsComponent)
  },

  // ── Admin ─────────────────────────────────────────────
  {
    path: 'admin/questions',
    canActivate: [authGuard, adminGuard],
    loadComponent: () => import('./modules/admin/questions/questions.component')
      .then(m => m.QuestionsComponent)
  },
  {
    path: 'admin/reports',
    canActivate: [authGuard, adminGuard],
    loadComponent: () => import('./modules/admin/reports/reports.component')
      .then(m => m.ReportsComponent)
  },

  {
  path: 'ranking',
  canActivate: [authGuard],
  loadComponent: () => import('./modules/ranking/ranking.component')
    .then(m => m.RankingComponent)
},
{
  path: 'admin/config',
  canActivate: [authGuard, adminGuard],
  loadComponent: () => import('./modules/admin/config/config.component')
    .then(m => m.ConfigComponent)
},
{
  path: 'admin/rooms',
  canActivate: [authGuard, adminGuard],
  loadComponent: () => import('./modules/admin/rooms/rooms.component')
    .then(m => m.RoomsComponent)
},
{
  path: 'admin/admins',
  canActivate: [authGuard, adminGuard],
  loadComponent: () => import('./modules/admin/admins/admins.component')
    .then(m => m.AdminsComponent)
},

  // ── Fallback ──────────────────────────────────────────
  { path: '**', redirectTo: 'login' }
];

