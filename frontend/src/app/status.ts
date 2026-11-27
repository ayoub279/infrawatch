export interface StatusSource {
 enabled: boolean;
 latest: { available: boolean; checked_at: string } | null;
}
export function statusOf(service: StatusSource, now = Date.now()): string {
 if (!service.enabled) return 'paused';
 if (!service.latest) return 'pending';
 const checkedAt = Date.parse(service.latest.checked_at);
 if (!Number.isFinite(checkedAt) || now - checkedAt > 180_000 || checkedAt - now > 60_000) return 'stale';
 return service.latest.available ? 'operational' : 'down';
}
