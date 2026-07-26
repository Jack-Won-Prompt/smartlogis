<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Enums\OrgType;
use App\Exports\ArrayHeadingExport;
use App\Exports\FailedRowsExport;
use App\Exports\OrganizationsExport;
use App\Http\Controllers\Controller;
use App\Imports\OrganizationsImport;
use App\Models\Organization;
use App\Support\ExcelFailReport;
use App\Support\ExcelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 거래처 마스터 그리드 백엔드.
 */
class OrganizationMasterController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignore = null): array
    {
        return [
            'org_type' => ['required', Rule::enum(OrgType::class)],
            'code' => ['required', 'string', 'max:30', Rule::unique('organizations', 'code')->ignore($ignore)],
            'name' => ['required', 'string', 'max:255'],
            'biz_reg_no' => ['nullable', 'string', 'max:20'],
            'hpid_no' => ['nullable', 'string', 'max:20'],
            'tel' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function data(Request $request): JsonResponse
    {
        $query = Organization::query()->withCount('users')->filter([
            'keyword' => $request->string('keyword')->toString() ?: null,
            'org_type' => $request->string('org_type')->toString() ?: null,
            'is_active' => $request->has('is_active') ? $request->string('is_active')->toString() : '',
        ]);

        $sort = $request->input('sort.0');
        if (is_array($sort) && in_array($sort['field'] ?? '', ['code', 'name', 'org_type'], true)) {
            $query->orderBy($sort['field'], ($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('code');
        }

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (Organization $o) => $this->row($o))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = array_merge(['is_active' => true], $request->all());
        $validated = Validator::make($data, $this->rules())->validate();
        $org = Organization::create($validated);

        return response()->json($this->row($org->loadCount('users')));
    }

    /**
     * wwGrid 배치 저장. 사용자 보유 거래처는 삭제 대신 비활성화(무결성 보호).
     */
    public function batch(Request $request): JsonResponse
    {
        $updated = $request->array('updated');
        $added = $request->array('added');
        $deleted = $request->array('deleted');
        $fields = ['org_type', 'code', 'name', 'biz_reg_no', 'hpid_no', 'tel', 'address', 'is_active'];
        $counts = ['added' => 0, 'updated' => 0, 'deleted' => 0];

        DB::transaction(function () use ($updated, $added, $deleted, $fields, &$counts) {
            $ids = collect($deleted)->pluck('id')->filter()->map(fn ($v) => (int) $v)->values()->all();
            if ($ids !== []) {
                $deletable = Organization::whereIn('id', $ids)->doesntHave('users')->pluck('id');
                $counts['deleted'] = Organization::whereIn('id', $deletable)->delete();
                Organization::whereIn('id', $ids)->whereNotIn('id', $deletable)->update(['is_active' => false]);
            }
            foreach ($added as $row) {
                $data = array_merge(['is_active' => true], Arr::only($row, $fields));
                $validated = Validator::make($data, $this->rules())->validate();
                Organization::create($validated);
                $counts['added']++;
            }
            foreach ($updated as $u) {
                $id = (int) ($u['current']['id'] ?? 0);
                $changed = Arr::only($u['changed'] ?? [], $fields);
                if ($id === 0 || $changed === []) {
                    continue;
                }
                $org = Organization::find($id);
                if ($org === null) {
                    continue;
                }
                $validated = Validator::make($changed, array_intersect_key($this->rules($id), $changed))->validate();
                $org->update($validated);
                $counts['updated']++;
            }
        });

        return response()->json(array_merge(['message' => '저장되었습니다.'], $counts));
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $field = $request->string('field')->toString();
        $rules = $this->rules($organization->id);
        if (! array_key_exists($field, $rules)) {
            return response()->json(['message' => '수정할 수 없는 항목입니다.'], 422);
        }
        $validated = Validator::make([$field => $request->input('value')], [$field => $rules[$field]])->validate();
        $organization->update($validated);

        return response()->json($this->row($organization->fresh()->loadCount('users')));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Organization::query()->withCount('users')->filter([
            'keyword' => $request->string('keyword')->toString() ?: null,
            'org_type' => $request->string('org_type')->toString() ?: null,
            'is_active' => $request->has('is_active') ? $request->string('is_active')->toString() : '',
        ])->orderBy('code');

        return Excel::download(new OrganizationsExport($query), ExcelFile::name('거래처'));
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ArrayHeadingExport(OrganizationsImport::columns()), ExcelFile::template('거래처'));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']], [], ['file' => '엑셀 파일']);
        $import = new OrganizationsImport;
        Excel::import($import, $request->file('file'));
        $report = $import->report();

        $failKey = null;
        if ($report->hasFailures()) {
            $failKey = 'ofail_'.uniqid();
            Session::put($failKey, $report);
        }

        return response()->json(['message' => $report->summaryMessage(), 'failed' => $report->failureCount(), 'failKey' => $failKey]);
    }

    public function failures(string $key): BinaryFileResponse
    {
        abort_unless(Session::has($key), 404);
        /** @var ExcelFailReport $report */
        $report = Session::get($key);

        return Excel::download(new FailedRowsExport($report, OrganizationsImport::columns()), ExcelFile::failures('거래처'));
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = array_map('intval', $request->input('ids', []));
        // 사용자가 있는 거래처는 삭제 대신 비활성화(무결성 보호)
        $deletable = Organization::whereIn('id', $ids)->doesntHave('users')->pluck('id');
        $deleted = Organization::whereIn('id', $deletable)->delete();
        $deactivated = Organization::whereIn('id', $ids)->whereNotIn('id', $deletable)->update(['is_active' => false]);

        return response()->json([
            'deleted' => $deleted,
            'message' => $deactivated > 0 ? "{$deleted}건 삭제, 사용자 보유 {$deactivated}건은 비활성화되었습니다." : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Organization $o): array
    {
        return [
            'id' => $o->id,
            'org_type' => $o->org_type->value,
            'org_type_label' => $o->org_type->label(),
            'code' => $o->code,
            'name' => $o->name,
            'biz_reg_no' => $o->biz_reg_no,
            'hpid_no' => $o->hpid_no,
            'tel' => $o->tel,
            'users_count' => $o->users_count ?? 0,
            'is_active' => (bool) $o->is_active,
        ];
    }
}
