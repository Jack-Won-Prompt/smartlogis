<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Exports\ArrayHeadingExport;
use App\Exports\FailedRowsExport;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Imports\UsersImport;
use App\Models\User;
use App\Support\ExcelFailReport;
use App\Support\ExcelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 사용자 마스터 그리드 백엔드. 인라인 편집 + 비밀번호 초기화.
 */
class UserMasterController extends Controller
{
    /**
     * 인라인 편집 가능한 필드 규칙(비밀번호 제외).
     *
     * @return array<string, mixed>
     */
    private function rules(?int $ignore = null): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],
            'login_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'login_id')->ignore($ignore)],
            'name' => ['required', 'string', 'max:50'],
            'role' => ['required', Rule::enum(OrgType::class)],
            'org_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'tel' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function data(Request $request): JsonResponse
    {
        $kw = $request->string('keyword')->toString();
        $query = User::query()->with('organization')
            ->when($kw, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$kw}%")->orWhere('login_id', 'like', "%{$kw}%")->orWhere('email', 'like', "%{$kw}%")))
            ->when($request->string('role')->toString(), fn ($q, $v) => $q->where('role', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v));

        $sort = $request->input('sort.0');
        if (is_array($sort) && in_array($sort['field'] ?? '', ['login_id', 'name'], true)) {
            $query->orderBy($sort['field'], ($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('login_id');
        }

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (User $u) => $this->row($u))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = array_merge(['status' => UserStatus::ACTIVE->value], $request->all());
        $validated = Validator::make($data, $this->rules())->validate();
        // login_id 미입력 시 이메일을 로그인 계정으로 사용
        if (empty($validated['login_id'])) {
            $validated['login_id'] = $validated['email'];
        }
        $validated['is_active'] = $validated['status'] === UserStatus::ACTIVE->value;
        if ($validated['status'] === UserStatus::ACTIVE->value) {
            $validated['approved_at'] = now();
        }

        // 임시 비밀번호 자동 발급 → 관리자에게 안내
        $temp = Str::password(10, symbols: false);
        $validated['password'] = Hash::make($temp);

        $user = User::create($validated);

        return response()->json(array_merge($this->row($user->load('organization')), ['temp_password' => $temp]));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $field = $request->string('field')->toString();
        $rules = $this->rules($user->id);
        if (! array_key_exists($field, $rules)) {
            return response()->json(['message' => '수정할 수 없는 항목입니다.'], 422);
        }
        $validated = Validator::make([$field => $request->input('value')], [$field => $rules[$field]])->validate();
        if ($field === 'status') {
            $validated['is_active'] = $validated['status'] === UserStatus::ACTIVE->value;
        }
        $user->update($validated);

        return response()->json($this->row($user->fresh()->load('organization')));
    }

    /** 임시 비밀번호 발급. */
    public function resetPassword(User $user): JsonResponse
    {
        $temp = Str::password(10, symbols: false);
        $user->update(['password' => Hash::make($temp)]);

        return response()->json(['temp' => $temp]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $kw = $request->string('keyword')->toString();
        $query = User::query()->with('organization')
            ->when($kw, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$kw}%")->orWhere('login_id', 'like', "%{$kw}%")->orWhere('email', 'like', "%{$kw}%")))
            ->when($request->string('role')->toString(), fn ($q, $v) => $q->where('role', $v))
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('login_id');

        return Excel::download(new UsersExport($query), ExcelFile::name('사용자'));
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ArrayHeadingExport(UsersImport::columns()), ExcelFile::template('사용자'));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']], [], ['file' => '엑셀 파일']);
        $import = new UsersImport;
        Excel::import($import, $request->file('file')->getRealPath());
        $report = $import->report();

        $failKey = null;
        if ($report->hasFailures()) {
            $failKey = 'ufail_'.uniqid();
            Session::put($failKey, $report);
        }

        return response()->json(['message' => $report->summaryMessage(), 'failed' => $report->failureCount(), 'failKey' => $failKey]);
    }

    public function failures(string $key): BinaryFileResponse
    {
        abort_unless(Session::has($key), 404);
        /** @var ExcelFailReport $report */
        $report = Session::get($key);

        return Excel::download(new FailedRowsExport($report, UsersImport::columns()), ExcelFile::failures('사용자'));
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = array_map('intval', $request->input('ids', []));
        // 자기 자신은 제외
        $ids = array_filter($ids, fn ($id) => $id !== $request->user()?->id);
        $deleted = User::whereIn('id', $ids)->delete();

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'login_id' => $u->login_id,
            'email' => $u->email,
            'name' => $u->name,
            'role' => $u->role->value,
            'role_label' => $u->role->label(),
            'org_id' => $u->org_id,
            'org_name' => $u->organization->name,
            'status' => $u->status->value,
            'status_label' => $u->status->label(),
            'password' => '',
        ];
    }
}
