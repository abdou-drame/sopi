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
        main { width: 100%; max-width: 440px; margin: 24px auto; padding: 28px 24px; background: #ffffff; border-top: 6px solid #1d4ed8; border-radius: 12px; box-shadow: 0 4px 16px rgba(29, 78, 216, 0.12); }
        .marque { margin: 0 0 16px; color: #1d4ed8; font-size: 1.1rem; font-weight: 700; letter-spacing: 0.05em; }
        h1 { margin: 0 0 8px; color: #1d4ed8; font-size: 1.4rem; }
        p { margin: 0 0 12px; line-height: 1.5; }
        .meta { color: #475569; font-size: 0.95rem; }
        label { display: block; margin: 16px 0 6px; font-weight: 600; font-size: 0.95rem; }
        input[type=password], input[type=text] { width: 100%; padding: 12px; border: 1px solid #94a3b8; border-radius: 8px; font-size: 1rem; }
        input:focus { outline: 3px solid #bfdbfe; border-color: #1d4ed8; }
        button { width: 100%; margin-top: 20px; padding: 14px; border: 0; border-radius: 8px; background: #1d4ed8; color: #ffffff; font-size: 1rem; font-weight: 700; cursor: pointer; }
        button:hover { background: #1e40af; }
        button:disabled { background: #93c5fd; cursor: wait; }
        .erreurs { margin: 16px 0 0; padding: 12px 16px; border-left: 6px solid #b91c1c; border-radius: 8px; background: #fef2f2; color: #7f1d1d; }
        .erreurs p { margin: 0; }
        .succes { margin: 16px 0 0; padding: 14px 16px; border-left: 6px solid #047857; border-radius: 8px; background: #ecfdf5; color: #065f46; }
        .regles { margin: 12px 0 0; padding: 0; list-style: none; font-size: 0.9rem; color: #475569; }
        .regles li { padding: 2px 0; }
        .regles li::before { content: "○ "; }
        .regles li.ok { color: #047857; }
        .regles li.ok::before { content: "✓ "; }
        .pied { margin-top: 24px; color: #64748b; font-size: 0.85rem; }
    </style>
</head>
<body>
    <main>
        <p class="marque">SOPI</p>
        @yield('contenu')
    </main>
</body>
</html>
