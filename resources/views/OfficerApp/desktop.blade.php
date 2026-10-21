{{-- Shown by EnsurePhone when /app is opened on something that is not a phone. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QSU SG Officers</title>
    @include('partials.favicon')
    <style>
        html { height: 100%; }
        body {
            box-sizing: border-box; min-height: 100%; margin: 0; display: grid; place-items: center; padding: 32px 24px;
            background: #f7f8fc; color: #4b5563; text-align: center; line-height: 1.5;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        main { max-width: 26rem; }
        img { width: 84px; height: 84px; border-radius: 20px; }
        h1 { margin: 20px 0 8px; color: #111827; font-size: 1.4rem; }
        p { margin: 0; }
        a {
            display: inline-flex; align-items: center; min-height: 48px; margin-top: 24px; padding: 0 24px;
            border-radius: 12px; background: #058028; color: #ffffff; font-weight: 600; text-decoration: none;
        }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('icons/officer-192.png') }}" alt="">
    <h1>This app is for phones</h1>
    <p>The QSU SG Officers app keeps files on the device for offline use, so it is offered on phones only. On this computer, use the website.</p>
    <a href="{{ url('/login') }}">Go to the website</a>
</main>
</body>
</html>
