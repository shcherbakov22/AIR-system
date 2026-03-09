<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f5efe3;
                --panel: rgba(255, 250, 240, 0.92);
                --ink: #17211c;
                --muted: #59645e;
                --accent: #a54c2f;
                --accent-soft: #d8854b;
                --border: rgba(23, 33, 28, 0.12);
                --shadow: rgba(66, 49, 34, 0.14);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: Georgia, "Times New Roman", serif;
                color: var(--ink);
                background:
                    radial-gradient(circle at top left, rgba(216, 133, 75, 0.28), transparent 32rem),
                    radial-gradient(circle at right 20%, rgba(165, 76, 47, 0.18), transparent 26rem),
                    linear-gradient(180deg, #f7f1e7 0%, var(--bg) 100%);
            }

            .shell {
                max-width: 980px;
                margin: 0 auto;
                padding: 48px 20px 72px;
            }

            .hero {
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: 24px;
                box-shadow: 0 24px 60px var(--shadow);
                overflow: hidden;
            }

            .hero-band {
                height: 14px;
                background: linear-gradient(90deg, var(--accent) 0%, var(--accent-soft) 100%);
            }

            .hero-body {
                padding: 32px;
            }

            .eyebrow {
                margin: 0 0 10px;
                color: var(--accent);
                font-size: 0.9rem;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                font-size: clamp(2.4rem, 6vw, 4.6rem);
                line-height: 0.95;
            }

            .lead {
                max-width: 42rem;
                margin: 18px 0 0;
                color: var(--muted);
                font-size: 1.08rem;
                line-height: 1.65;
            }

            .grid {
                display: grid;
                gap: 18px;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                margin-top: 28px;
            }

            .card {
                padding: 18px;
                border-radius: 18px;
                border: 1px solid var(--border);
                background: rgba(255, 255, 255, 0.72);
            }

            .card h2 {
                margin: 0 0 8px;
                font-size: 1.05rem;
            }

            .card p,
            .card li {
                margin: 0;
                color: var(--muted);
                line-height: 1.55;
            }

            .card ul {
                margin: 0;
                padding-left: 18px;
            }

            .footer {
                margin-top: 20px;
                color: var(--muted);
                font-size: 0.95rem;
            }

            code {
                font-family: "Cascadia Mono", Consolas, monospace;
                font-size: 0.95em;
                background: rgba(23, 33, 28, 0.06);
                padding: 0.1rem 0.32rem;
                border-radius: 0.35rem;
            }
        </style>
    </head>
    <body>
        <main class="shell">
            <section class="hero">
                <div class="hero-band"></div>
                <div class="hero-body">
                    <p class="eyebrow">Platform Baseline</p>
                    <h1>{{ config('app.name') }}</h1>
                    <p class="lead">
                        The rewrite is online. This page only confirms that the new platform boots with the intended
                        runtime direction. Student workflows, schedules, violations, penalties, monitoring, and device
                        integration still need to be implemented.
                    </p>

                    <div class="grid">
                        <article class="card">
                            <h2>Runtime</h2>
                            <ul>
                                <li>Laravel {{ app()->version() }}</li>
                                <li>PHP {{ PHP_VERSION }}</li>
                                <li>Environment: {{ app()->environment() }}</li>
                            </ul>
                        </article>

                        <article class="card">
                            <h2>Database Target</h2>
                            <ul>
                                <li>Connection: {{ config('database.default') }}</li>
                                <li>Host: {{ config('database.connections.pgsql.host') }}</li>
                                <li>Port: {{ config('database.connections.pgsql.port') }}</li>
                            </ul>
                        </article>

                        <article class="card">
                            <h2>Next Domain Work</h2>
                            <p>
                                Admin identity, student records, tasks, schedules, sessions, rules, violations, and the
                                penalty ledger are still pending.
                            </p>
                        </article>
                    </div>

                    <p class="footer">
                        Local setup expects the repo helper:
                        <code>tools/scripts/dev/postgres.ps1 ensure</code>
                    </p>
                </div>
            </section>
        </main>
    </body>
</html>
