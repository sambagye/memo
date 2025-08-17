<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ISI - Plateforme Mémoires')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #1e40af;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background-color: #1e40af;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 15px 0;
        }
        .button:hover {
            background-color: #1e3a8a;
        }
        .info-box {
            background-color: #e0f2fe;
            border-left: 4px solid #0891b2;
            padding: 15px;
            margin: 15px 0;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 15px 0;
        }
        .success-box {
            background-color: #dcfce7;
            border-left: 4px solid #22c55e;
            padding: 15px;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>Institut Supérieur d'Informatique</h1>
    <p>Plateforme de Gestion des Mémoires</p>
</div>

<div class="content">
    @yield('content')
</div>

<div class="footer">
    <p>
        Cet email a été envoyé automatiquement. Merci de ne pas répondre à cette adresse.<br>
        Pour toute question, contactez l'administration à admin@isi.sn
    </p>
    <p>&copy; {{ date('Y') }} Institut Supérieur d'Informatique - Tous droits réservés</p>
</div>
</body>
</html>
