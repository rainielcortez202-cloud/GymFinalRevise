FROM php:8.2-apache

# Fix Apache MPM conflict
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork

# Install system dependencies
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxpm-dev \
        libonig-dev \
        libicu-dev \
        libcurl4-openssl-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install pdo pdo_pgsql pgsql gd calendar curl mbstring intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Environment configuration
ENV BREVO_API_KEY=""
ENV SUPABASE_URL=""
ENV SUPABASE_ANON_KEY=""
ENV SUPABASE_SERVICE_ROLE_KEY=""
ENV SUPABASE_DB_HOST=""
ENV SUPABASE_DB_PORT="6543"
ENV SUPABASE_DB_NAME="postgres"
ENV SUPABASE_DB_USER=""
ENV SUPABASE_DB_PASSWORD=""
ENV ALLOW_DB_IMPORT="0"

# Copy project files
COPY . /var/www/html/

WORKDIR /var/www/html

# Install Composer dependencies
RUN if [ -f composer.json ]; then composer install; fi

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
