<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\CheckPresenter;
use App\Services\DestinationPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ServiceController
{
    public function index()
    {
        return DB::table('services')->orderBy('id')->get()->map(function ($s) {
            $s->enabled = (bool) $s->enabled;
            $s->latest = CheckPresenter::present(DB::table('checks')->where('service_id', $s->id)->where('target_url', $s->url)->latest('id')->first());

            return $s;
        });
    }

    public function history(int $id)
    {
        abort_unless(DB::table('services')->where('id', $id)->exists(), 404);

        return DB::table('checks')->where('service_id', $id)->latest('id')->limit(60)->get()->map(fn ($c) => CheckPresenter::present($c));
    }

    private function data(Request $r, DestinationPolicy $policy): array
    {
        $data = $r->validate(['name' => 'required|string|max:80', 'url' => 'required|string|max:2048', 'enabled' => 'required|boolean']);
        try {
            $policy->parse($data['url']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        return $data;
    }

    public function store(Request $r, DestinationPolicy $policy)
    {
        $data = $this->data($r, $policy);

        return DB::transaction(function () use ($data, $r) {
            DB::table('users')->where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            abort_if(DB::table('services')->count() >= 50, 422, 'Service limit reached.');
            $id = DB::table('services')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            Audit::record($r->user()->id, 'service.created', $id, ['name' => $data['name']]);

            return response()->json(['id' => $id], 201);
        });
    }

    public function update(Request $r, int $id, DestinationPolicy $policy)
    {
        $data = $this->data($r, $policy);

        return DB::transaction(function () use ($r, $id, $data) {
            abort_unless(DB::table('services')->where('id', $id)->lockForUpdate()->first(), 404);
            DB::table('services')->where('id', $id)->update($data + ['updated_at' => now()]);
            Audit::record($r->user()->id, 'service.updated', $id, ['name' => $data['name'], 'enabled' => $data['enabled']]);

            return response()->noContent();
        });
    }

    public function destroy(Request $r, int $id)
    {
        return DB::transaction(function () use ($r, $id) {
            abort_unless(DB::table('services')->where('id', $id)->delete(), 404);
            Audit::record($r->user()->id, 'service.deleted', $id);

            return response()->noContent();
        });
    }
}
