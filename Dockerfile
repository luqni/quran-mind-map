# Stage 1: Build Frontend Assets
FROM node:20 as build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: Production Runtime
FROM serversideup/php:8.2-fpm-nginx as production

# Set working directory to standard web root
WORKDIR /var/www/html

# Switch to root to install system dependencies
USER root

# Install dependencies required for Composer and build tools
# serversideup/php image comes with xml, zip, bcmath, intl pre-installed!
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Switch back to web user
USER www-data

# Copy application files
COPY --chown=www-data:www-data . .

# Copy built assets from the build stage
# Ensure correct path for Vite build output (usually public/build)
COPY --from=build --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Install Composer dependencies optimized for production
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy .env.example if .env is missing (Environment variables should be set in Easypanel)
RUN cp .env.example .env || true

# Generate application key if not set (Though APP_KEY should be an env var)
# RUN php artisan key:generate

# Fix permissions for storage and cache (Critical for 500 errors)
USER root
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
USER www-data

# SHOW ERRORS for debugging (EasyPanel captures stderr)
ENV PHP_DISPLAY_ERRORS=On
ENV PHP_ERROR_REPORTING="E_ALL"

# Expose port 8080 (Default for serversideup/php)
EXPOSE 8080
