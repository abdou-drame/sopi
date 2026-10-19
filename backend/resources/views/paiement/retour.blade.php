<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $titre }} · Sopi</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: #eff6ff;
            color: #0f172a;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main {
            width: 100%;
            max-width: 420px;
            padding: 32px 24px;
            background: #ffffff;
            border-top: 6px solid #1d4ed8;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(29, 78, 216, 0.12);
            text-align: center;
        }
        .marque { display: flex; align-items: center; gap: 10px; margin: 0 0 16px; color: #1d4ed8; font-size: 1.1rem; font-weight: 700; letter-spacing: 0.05em; }
        .logo { width: 32px; height: 32px; border-radius: 8px; }
        h1 { margin: 0 0 12px; color: #1d4ed8; font-size: 1.5rem; }
        p { margin: 0; line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <p class="marque"><img src="/logo/sopi_icon_rounded_96.png" alt="" width="32" height="32" class="logo"> Sopi</p>
        <h1>{{ $titre }}</h1>
        <p>{{ $message }}</p>
    </main>
</body>
</html>
