<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Documento' }}</title>

    <!-- Favicons de la marca -->
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" href="/logo-provisorio-papelera.png?v=2" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #f8fafc;
            -webkit-font-smoothing: antialiased;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        .fallback-message {
            color: #f8fafc;
            padding: 24px;
            font-size: 14px;
            line-height: 1.5;
            text-align: center;
        }
        .fallback-link {
            color: #38bdf8;
            text-decoration: underline;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <iframe src="{{ $streamUrl }}" title="{{ $title ?? 'Documento PDF' }}">
        <p class="fallback-message">
            Tu navegador no soporta la visualización directa de documentos PDF.
            <a href="{{ $streamUrl }}" class="fallback-link">Haz clic aquí para abrir o descargar el documento.</a>
        </p>
    </iframe>
</body>
</html>
