<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class IncidentController
{
    public function index()
    {
        return DB::table('incidents')->join('services', 'services.id', '=', 'incidents.service_id')
            ->select('incidents.*', 'services.name as service_name')
            ->orderByRaw('CASE WHEN incidents.resolved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('incidents.id')->limit(100)->get()->map(function ($i) {
                foreach (['opened_at', 'acknowledged_at', 'resolved_at'] as $field) {
                    if ($i->$field) {
                        $i->$field = Carbon::parse($i->$field)->utc()->toISOString();
                    }
                }

                return $i;
            });
    }

    public function transition(Request $r, int $id)
    {
        $action = $r->validate(['action' => 'required|in:acknowledge,resolve'])['action'];

        return DB::transaction(function () use ($r, $id, $action) {
            $i = DB::table('incidents')->where('id', $id)->lockForUpdate()->first();
            abort_unless($i, 404);
            abort_if($i->resolved_at || ($action === 'acknowledge' && $i->acknowledged_at), 409, 'Incident already transitioned.');
            $values = $action === 'resolve' ? ['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $r->user()->id]
             : ['status' => 'acknowledged', 'acknowledged_at' => now(), 'acknowledged_by' => $r->user()->id];
            DB::table('incidents')->where('id', $id)->update($values);
            Audit::record($r->user()->id, 'incident.'.$action, $id);

            return response()->noContent();
        });
    }
}
