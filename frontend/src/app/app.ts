import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { statusOf } from './status';

interface User { name: string; email: string; role: 'admin' | 'viewer'; }
interface Check { id: number; available: boolean; http_status: number | null; response_ms: number; checked_at: string; tls_expires_at: string | null; dns_addresses: string | string[]; error: string | null; }
interface Service { id: number; name: string; url: string; enabled: boolean; latest: Check | null; }
interface Incident { id: number; service_name: string; status: string; opened_at: string; }
interface Audit { id: number; actor_id: number | null; action: string; subject_id: number; created_at: string; }
@Component({
 selector: 'app-root', imports: [CommonModule, FormsModule], templateUrl: './app.html', styleUrl: './app.css'
})
export class App implements OnInit, OnDestroy {
 private http = inject(HttpClient);
 user = signal<User | null>(null); services = signal<Service[]>([]); incidents = signal<Incident[]>([]);
 audits = signal<Audit[]>([]); hosts = signal<string[]>([]); checks = signal<Check[]>([]);
 loading = signal(true); busy = signal(false); error = signal(''); page = signal('overview');
 selected = signal<Service | null>(null); editing = signal(false);
 email = ''; password = ''; form = { name: '', url: '', enabled: true }; editId: number | null = null;
 updated = signal<Date | null>(null); private timer?: ReturnType<typeof setInterval>;
 private clearSession() {
  this.user.set(null); this.services.set([]); this.incidents.set([]); this.audits.set([]);
  this.hosts.set([]); this.checks.set([]); this.selected.set(null); this.editing.set(false);
  this.page.set('overview'); this.password = '';
 }
 async ngOnInit() {
  try { this.user.set(await this.get<User>('/me')); await this.refresh(); }
  catch (e) { if (!(e instanceof HttpErrorResponse && e.status === 401)) this.showError(e); }
  finally { this.loading.set(false); }
  this.timer = setInterval(() => { if (this.user() && !this.busy()) void this.refresh().catch(e => this.showError(e)); }, 15000);
 }
 ngOnDestroy() { clearInterval(this.timer); }
 private get<T>(path: string) { return firstValueFrom(this.http.get<T>('/api' + path)); }
 private showError(e: unknown) {
  if (e instanceof HttpErrorResponse && [401,419].includes(e.status)) {
   this.clearSession(); this.error.set('Session expired. Please sign in again.'); return;
  }
  const body = e instanceof HttpErrorResponse ? e.error : null;
  this.error.set(body?.errors ? Object.values(body.errors).flat().join(' ') : body?.message || 'Request failed. Check the API connection and try again.');
 }
 async login() {
  const credentials = { email: this.email, password: this.password };
  this.clearSession();
  this.busy.set(true); this.error.set('');
  try {
   await this.get('/csrf');
   this.user.set(await firstValueFrom(this.http.post<User>('/api/login', credentials)));
   this.password = ''; await this.refresh();
  } catch (e) { this.showError(e); this.password = ''; } finally { this.busy.set(false); }
 }
 async logout() {
  this.busy.set(true);
  try { await firstValueFrom(this.http.post('/api/logout', {})); this.clearSession(); this.error.set(''); }
  catch(e) { this.showError(e); } finally { this.busy.set(false); }
 }
 async refresh() {
  const current = this.user(); if (!current) return;
  const [services, incidents, hosts, audits] = await Promise.all([
   this.get<Service[]>('/services'), this.get<Incident[]>('/incidents'),
   current.role === 'admin' ? this.get<string[]>('/allowed-hosts') : Promise.resolve([]),
   current.role === 'admin' ? this.get<Audit[]>('/audit') : Promise.resolve([])
  ]);
  if (this.user() !== current) return; // Ignore requests from a signed-out session.
  this.services.set(services); this.incidents.set(incidents); this.hosts.set(hosts); this.audits.set(audits); this.updated.set(new Date());
  const selection = this.selected(); if (selection) this.checks.set(await this.get<Check[]>('/services/' + selection.id + '/checks'));
 }
 state(s: Service): string {
  return statusOf(s);
 }
 count(status: string) { return this.services().filter(s => this.state(s) === status).length; }
 activeIncidents() { return this.incidents().filter(i => i.status !== 'resolved').length; }
 async history(s: Service) {
  this.error.set(''); this.selected.set(s); this.checks.set([]);
  try { this.checks.set(await this.get<Check[]>('/services/' + s.id + '/checks')); } catch(e) { this.showError(e); }
 }
 edit(s?: Service) {
  this.editId = s?.id ?? null;
  this.form = s ? { name: s.name, url: s.url, enabled: s.enabled } : { name: '', url: 'https://', enabled: true };
  this.editing.set(true); this.error.set('');
 }
 async save() {
  this.busy.set(true); this.error.set('');
  try {
   const url = '/api/services' + (this.editId ? '/' + this.editId : '');
   await firstValueFrom(this.editId ? this.http.put(url,this.form) : this.http.post(url,this.form));
   this.editing.set(false); await this.refresh();
  } catch(e) { this.showError(e); } finally { this.busy.set(false); }
 }
 async remove(s: Service) {
  if (!confirm('Delete ' + s.name + ' and its check and incident history?')) return;
  this.busy.set(true); this.error.set('');
  try { await firstValueFrom(this.http.delete('/api/services/' + s.id)); if (this.selected()?.id === s.id) this.selected.set(null); await this.refresh(); }
  catch(e) { this.showError(e); } finally { this.busy.set(false); }
 }
 async transition(i: Incident, action: string) {
  this.busy.set(true); this.error.set('');
  try { await firstValueFrom(this.http.patch('/api/incidents/' + i.id,{action})); await this.refresh(); }
  catch(e) { this.showError(e); } finally { this.busy.set(false); }
 }
}
