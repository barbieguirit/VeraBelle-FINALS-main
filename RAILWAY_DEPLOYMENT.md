# Railway Deployment Guide

## Prerequisites
- GitHub repository connected to Railway
- MySQL database provisioned on Railway

## Environment Variables Required

Set these in Railway dashboard under **Variables**:

```
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=your-secret-key-here

# Database (provided by Railway MySQL plugin)
DATABASE_URL=mysql://user:password@hostname:3306/dbname

# JWT Authentication
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-jwt-passphrase-here

# Railway build will create config/jwt/private.pem and public.pem automatically
# when they are missing. If you need stable keys across redeploys, provide the
# PEM contents through JWT_PRIVATE and JWT_PUBLIC instead.

# CORS for mobile app
CORS_ALLOW_ORIGIN=^https?://localhost(:[0-9]+)?$|^https://yourmobiledomain\.com$

# Google OAuth (if using)
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret

# API Platform
API_ENTRYPOINT=https://your-railway-domain.com/api

# Session settings
SESSION_HANDLER=database
```

## Deployment Steps

1. **Connect GitHub Repository**
   - Go to Railway dashboard
   - Click "New Project"
   - Select "Deploy from GitHub repo"
   - Choose `thv-maker/midterm-project`

2. **Add MySQL Database**
   - Click "Add Service"
   - Select "MySQL"
   - Railway will auto-populate `DATABASE_URL` variable

3. **Set Environment Variables**
   - In your project settings, add all variables from the "Environment Variables Required" section above
   - Generate a strong `APP_SECRET` (32+ random characters)
   - Create JWT keypair or use existing from `config/jwt/`

4. **Deploy**
   - Railway auto-deploys on push to main branch
   - Watch deployment logs in dashboard
   - First deployment runs migrations automatically

5. **Post-Deployment**
   - Test API endpoint: `https://your-railway-domain.com/api`
   - Update mobile app BASE_URL to Railway domain
   - Check logs if issues occur

## Troubleshooting

- **Migrations fail**: Check `DATABASE_URL` is correct
- **JWT errors**: Ensure JWT keys are in `config/jwt/`
- **CORS errors**: Update `CORS_ALLOW_ORIGIN` for your mobile domain
- **Static files 404**: Check `public/` directory is accessible

## Mobile App Configuration

After deployment, update the mobile app's API base URL:

**File: `src/app/api/orders.js`**

```javascript
const BASE_URL = 'https://your-railway-domain.com/api/customer';
```

Replace `your-railway-domain.com` with your actual Railway domain from the dashboard.