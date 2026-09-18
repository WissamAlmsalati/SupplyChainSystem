<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>{{ $title }} — الساحل API</title>
    <style>
        /* Descriptions are written in Arabic; let them read right-to-left
           without flipping the reference layout, which is built for LTR. */
        .scalar-app [dir="auto"], .scalar-app p:lang(ar) { text-align: start; }
        .scalar-app .markdown p, .scalar-app .markdown li { unicode-bidi: plaintext; }
    </style>
</head>
<body>
    <script id="api-reference" data-url="{{ $url }}" data-configuration="{{ json_encode($configuration ?? [
        'theme' => 'kepler',
        'layout' => 'modern',
        'searchHotKey' => 'k',
        'hideDownloadButton' => false,
        'darkMode' => false,
        // Most people here reach for curl first; the rest are one click away.
        'defaultHttpClient' => ['targetKey' => 'shell', 'clientKey' => 'curl'],
        'authentication' => ['preferredSecurityScheme' => 'bearerAuth'],
        'metaData' => ['title' => $title.' — الساحل API'],
    ], JSON_UNESCAPED_UNICODE) }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference@1.28.11"></script>
</body>
</html>
