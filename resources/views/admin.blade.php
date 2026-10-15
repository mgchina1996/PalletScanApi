<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entries 管理 · PalletScan Admin</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body>
<div id="admin-app" data-today="{{ now(\App\Http\Resources\EntryListResource::TIMEZONE)->toDateString() }}" data-config="{{ json_encode([
    'home' => route('admin.index'),
    'section' => route('admin.section', ['section' => '__SECTION__']),
    'summary' => route('admin.summary'),
    'entries' => route('admin.entries'),
    'detail' => route('admin.show', ['entry' => '__ID__']),
    'generate' => route('admin.generate', ['entry' => '__ID__']),
]) }}">
    <aside class="sidebar">
        <a class="brand" href="{{ route('admin.index') }}"><span class="brand-icon">▣</span> PalletScan Admin</a>
        <nav aria-label="后台导航">
            <a data-section="overview" href="{{ route('admin.index') }}"><span>⌂</span> 总览</a>
            <a data-section="small-parce" href="{{ route('admin.section', ['section' => 'small-parce']) }}"><span>▣</span> Small Parce</a>
            <a data-section="ltl" href="{{ route('admin.section', ['section' => 'ltl']) }}"><span>▱</span> LTL</a>
            <a data-section="exceptions" href="{{ route('admin.section', ['section' => 'exceptions']) }}"><span>⚠</span> 数据异常 <b id="exception-count">—</b></a>
        </nav>
        <div class="sidebar-note">ENTRIES WORKSPACE<br><small>库存处理管理中心</small></div>
    </aside>
    <div class="workspace">
        <header class="topbar"><span>仓库运营 / Entries</span><div><span>● 管理员</span><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="text-button">退出</button></form></div></header>
        <main id="content" aria-live="polite"><div class="empty">正在加载…</div></main>
    </div>
    <div id="notice" role="alert" hidden></div>
    <dialog id="detail-dialog" class="drawer" aria-label="Entry 详情"></dialog>
    <dialog id="batch-dialog" aria-label="库存生成"></dialog>
</div>
<noscript>请启用 JavaScript 后使用后台。</noscript>
</body>
</html>
