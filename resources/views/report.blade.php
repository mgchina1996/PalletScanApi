<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2550">
    <title>Inventory Entry Report · PalletScan</title>
    @vite(['resources/css/app.css', 'resources/js/report.js'])
</head>
<body class="min-h-dvh bg-slate-100 text-slate-950 antialiased">
    <div id="report-app" class="min-h-dvh" data-today="{{ now('America/Los_Angeles')->toDateString() }}"></div>
    <noscript>
        <div class="mx-auto max-w-xl p-8 text-center">This report requires JavaScript to run.</div>
    </noscript>
</body>
</html>
