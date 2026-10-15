<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminEntriesRequest;
use App\Http\Resources\AdminEntryResource;
use App\Http\Resources\EntryListResource;
use App\Models\Entry;
use App\Services\AdminEntryService;
use App\Services\StockGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use OSS\OssClient;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    public function login(): View
    {
        return view('admin-login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:1024']]);
        $hash = (string) config('services.admin.password_hash');
        if ($hash === '' || ! hash_equals((string) config('services.admin.username'), $data['username']) || ! password_verify($data['password'], $hash)) {
            return back()->withErrors(['username' => '账号或密码错误，或后台账号尚未配置。'])->withInput($request->only('username'));
        }
        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);

        return redirect()->route('admin.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function index(): View
    {
        return view('admin');
    }

    public function summary(AdminEntryService $entries): JsonResponse
    {
        return response()->json($entries->summary());
    }

    public function entries(AdminEntriesRequest $request, AdminEntryService $entries): AnonymousResourceCollection
    {
        $data = $request->validated();
        $query = $entries->query($data['zone']);
        $entries->scopeStatus($query, $data['status'] ?? 'pending');
        foreach (['type' => 'type', 'location' => 'location_code'] as $key => $column) {
            if (! empty($data[$key])) {
                $query->where($column, $data[$key]);
            }
        }
        if (! empty($data['prefix'])) {
            $query->where('location_code', 'like', $data['prefix'].'-%');
        }
        if (! empty($data['code'])) {
            $query->where('code', 'like', '%'.$data['code'].'%');
        }
        if (! empty($data['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($data['from'], EntryListResource::TIMEZONE)->utc());
        }
        if (! empty($data['to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($data['to'], EntryListResource::TIMEZONE)->addDay()->utc());
        }

        return AdminEntryResource::collection($query->orderByDesc('id')->paginate($data['per_page'] ?? 10));
    }

    public function show(Entry $entry, AdminEntryService $entries): AdminEntryResource
    {
        abort_if($entry->is_deleted, 404);

        $resource = new AdminEntryResource($entry->load(['products', 'stockGenerationLogs' => fn ($query) => $query->orderByDesc('id')]));

        try {
            $resource->inventory = $entries->cartonInventory($entry);
        } catch (\Throwable $exception) {
            report($exception);
            $resource->inventoryError = 'Portal 库存读取失败，请稍后重新打开详情。';
        }

        return $resource;
    }

    public function generate(Request $request, Entry $entry, AdminEntryService $entries, StockGenerationService $service): JsonResponse
    {
        $data = $request->validate(['zone' => ['required', 'in:small-parce,ltl']]);
        $lock = Cache::lock('stock-generation:'.$entry->id, 600);
        abort_unless($lock->get(), 409, '此记录正在处理，请稍后刷新。');

        try {
            $entry = $entries->query($data['zone'])->findOrFail($entry->id);
            abort_if((bool) $entry->stock_generated, 409, '此记录已成功生成库存，不能重复处理。');
            $service->generate($entry);
            $entry->refresh();

            return response()->json(['data' => (new AdminEntryResource($entry))->resolve()]);
        } finally {
            $lock->release();
        }
    }

    public function image(Entry $entry): Response
    {
        abort_if($entry->is_deleted || ! $entry->image_path, 404);
        $config = config('services.aliyun_oss');
        abort_if(empty($config['access_key_id']) || empty($config['access_key_secret']) || empty($config['endpoint']) || empty($config['bucket']), 503, '图片存储尚未配置。');
        $client = new OssClient($config['access_key_id'], $config['access_key_secret'], $config['endpoint']);
        $content = $client->getObject($config['bucket'], $entry->image_path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        return response($content)->header('Content-Type', $mime)->header('Cache-Control', 'private, max-age=300')->header('X-Content-Type-Options', 'nosniff');
    }
}
