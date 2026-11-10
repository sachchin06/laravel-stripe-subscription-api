<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px;
               font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f6f7f9; color: #1f2933; }
        main { max-width: 32rem; background: #fff; border-radius: 12px; padding: 2rem;
               box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        p { line-height: 1.5; margin-bottom: 0; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
    </main>
</body>
</html>
