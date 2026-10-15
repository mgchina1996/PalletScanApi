<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理员登录 · PalletScan</title>
    @vite(['resources/css/admin.css'])
</head>
<body class="login-page">
    <form class="login-card" method="post" action="{{ route('admin.authenticate') }}">
        @csrf
        <div class="brand-icon">▣</div>
        <h1>PalletScan Admin</h1>
        <p class="muted">登录库存处理管理中心</p>
        @if ($errors->any())<p class="error-box" role="alert">{{ $errors->first() }}</p>@endif
        <label>账号<input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></label>
        <label>密码<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="primary" type="submit">登录</button>
    </form>
</body>
</html>
