<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delegate Mobile API - Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="/docs/asset/swagger-ui.css">
    <link rel="icon" type="image/png" href="/docs/asset/favicon-32x32.png" sizes="32x32"/>
    <link rel="icon" type="image/png" href="/docs/asset/favicon-16x16.png" sizes="16x16"/>
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="/docs/asset/swagger-ui-bundle.js"></script>
    <script src="/docs/asset/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: "{{ route('swagger.delegate.json') }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        }
    </script>
</body>
</html>
