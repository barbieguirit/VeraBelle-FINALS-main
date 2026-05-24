Quick Railway deployment steps

1) Create a new project on Railway and add a MySQL plugin (or use Railway's managed database). Note the `DATABASE_URL` provided by Railway.

2) In Railway > Project > Variables, set the environment variables listed in `railway.env.example`. Important items:
- `DATABASE_URL` — use Railway's MySQL connection string
- `APP_SECRET`, `MAILER_DSN`, `ADMIN_EMAIL`, etc.
- For JWT keys: generate a keypair locally (see next step) and set `JWT_PRIVATE_KEY` and `JWT_PUBLIC_KEY` contents as Railway variables (store as multiline values).

3) Generate JWT keys locally (do NOT commit them). From your machine run:

```bash
php scripts/generate_jwt_keys.php
```

Copy contents of `config/jwt/private.pem` and `config/jwt/public.pem` into Railway variables (e.g. `JWT_PRIVATE_KEY`, `JWT_PUBLIC_KEY`) or store them in Railway's secrets.

4) Deploy using the Dockerfile in the repo root. In Railway you can add a service using the Dockerfile, or push to a Git repo connected to Railway and use the Dockerfile build.

5) Set the build and start commands if needed:
- Build: none (Dockerfile handles build).
- Start: container listens on port 80 (Railway will detect automatically).

6) After deployment, run migrations (if needed) via Railway's run command or connect via Railway CLI and run:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Notes:
- Remove secrets from committed `.env` files; use Railway variables instead. Replace `GOOGLE_CLIENT_SECRET` and other secrets with Railway variables.
- If you prefer a simpler Symfony runtime, use `php:8.2-fpm` + nginx in Dockerfile instead.
