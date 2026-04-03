<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening Database</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f5f4;
            color: #1c1917;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        main {
            width: min(30rem, calc(100vw - 2rem));
            padding: 1.5rem;
            border: 1px solid #d6d3d1;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(28, 25, 23, 0.08);
        }

        h1 {
            margin: 0 0 0.75rem;
            font-size: 1.125rem;
        }

        p {
            margin: 0;
            line-height: 1.5;
        }

        .error {
            margin-top: 1rem;
            color: #991b1b;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <main>
        <h1>Opening database</h1>
        <p id="status">Signing into Adminer…</p>
        <p id="error" class="error" hidden></p>
    </main>

    <form id="adminer-login" method="post" action="/adminer.php" hidden>
        <input type="hidden" name="auth[driver]" value="{{ $driver }}">
        <input type="hidden" name="auth[server]" value="{{ $server }}">
        <input type="hidden" name="auth[username]" value="{{ $username }}">
        <input type="hidden" name="auth[password]" value="{{ $password }}">
        <input type="hidden" name="auth[db]" value="{{ $database }}">
        <input type="hidden" name="auth[permanent]" value="1">
    </form>

    <script>
        (async () => {
            const status = document.getElementById('status');
            const error = document.getElementById('error');
            const form = document.getElementById('adminer-login');

            try {
                const response = await fetch('/adminer.php', {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const token = doc.querySelector('input[name="token"]')?.value ?? '';

                if (! token) {
                    throw new Error('Adminer token was not found.');
                }

                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'token';
                tokenInput.value = token;
                form.appendChild(tokenInput);

                status.textContent = 'Redirecting to Adminer…';
                form.submit();
            } catch (exception) {
                status.textContent = 'Adminer auto-login failed.';
                error.hidden = false;
                error.textContent = exception instanceof Error
                    ? exception.message
                    : 'Unknown Adminer login error.';
            }
        })();
    </script>
</body>
</html>
