<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $titre }}</title>
</head>
<body style="margin:0;padding:0;background:#eef4ff;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4ff;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px;overflow:hidden;">
<tr><td style="background:#1d4ed8;color:#ffffff;padding:20px 24px;font-size:22px;font-weight:bold;">Sopi</td></tr>
<tr><td style="padding:24px;">
<p style="margin:0 0 12px;font-size:16px;">Bonjour {{ $prenom }},</p>
<p style="margin:0 0 16px;font-size:14px;color:#475569;">{{ $accroche }}</p>
<h1 style="margin:0 0 12px;font-size:18px;color:#1d4ed8;">{{ $titre }}</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.5;">{{ $corps }}</p>
@if (! empty($lien))
<p style="margin:0 0 20px;"><a href="{{ $lien }}" style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-size:15px;font-weight:bold;">{{ $libelleLien }}</a></p>
<p style="margin:0 0 12px;font-size:13px;color:#475569;word-break:break-all;">Si le bouton ne s'affiche pas, copiez ce lien dans votre navigateur : {{ $lien }}</p>
<p style="margin:0;font-size:14px;color:#475569;">{{ $noteLien }}</p>
@else
<p style="margin:0;font-size:14px;color:#475569;">Ouvrez l'application Sopi pour voir le détail.</p>
@endif
</td></tr>
<tr><td style="padding:16px 24px;background:#f8fafc;font-size:12px;color:#64748b;">Ce message est envoyé automatiquement, merci de ne pas y répondre. — L'équipe Sopi</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
