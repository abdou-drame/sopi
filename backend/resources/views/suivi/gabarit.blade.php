<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('titre') · Sopi</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 16px; background: #eff6ff; color: #0f172a; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { width: 100%; max-width: 520px; margin: 24px auto; padding: 28px 24px; background: #ffffff; border-top: 6px solid #1d4ed8; border-radius: 12px; box-shadow: 0 4px 16px rgba(29, 78, 216, 0.12); }
        .marque { display: flex; align-items: center; gap: 10px; margin: 0 0 16px; color: #1d4ed8; font-size: 1.1rem; font-weight: 700; letter-spacing: 0.05em; }
        .logo { width: 32px; height: 32px; border-radius: 8px; }
        h1 { margin: 0 0 4px; color: #1d4ed8; font-size: 1.4rem; overflow-wrap: anywhere; }
        h2 { margin: 24px 0 8px; font-size: 1rem; color: #1e293b; }
        p { margin: 0; line-height: 1.5; }
        .meta { color: #475569; font-size: 0.95rem; }
        .bandeau { margin-top: 20px; padding: 14px 16px; border-radius: 8px; border-left: 6px solid; }
        .bandeau strong { display: block; margin-bottom: 4px; font-size: 1.05rem; }
        .attente { background: #eff6ff; border-color: #1d4ed8; color: #1e3a8a; }
        .accepte { background: #ecfdf5; border-color: #047857; color: #065f46; }
        .refuse { background: #fef2f2; border-color: #b91c1c; color: #7f1d1d; }
        ul { margin: 0; padding: 0; list-style: none; }
        li { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        li:last-child { border-bottom: 0; }
        .pastille { flex: none; align-self: flex-start; padding: 2px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        li .attente { background: #dbeafe; } li .accepte { background: #d1fae5; } li .refuse { background: #fee2e2; }
        .motif { display: block; margin-top: 2px; color: #7f1d1d; font-size: 0.9rem; }
        .pied { margin-top: 24px; color: #64748b; font-size: 0.85rem; }
    </style>
</head>
<body>
    <main>
        <p class="marque"><img src="/logo/sopi_icon_rounded_96.png" alt="" width="32" height="32" class="logo"> Sopi</p>
        @yield('contenu')
    </main>
</body>
</html>
