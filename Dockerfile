FROM php:8.3-cli

# System dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    git curl unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && printf "memory_limit=1G\nmax_execution_time=0\n" > /usr/local/etc/php/conf.d/app.ini

# Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Frontend dependencies + build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Post-install scripts
RUN composer dump-autoload --optimize

COPY docker-start.sh /app/docker-start.sh
RUN chmod +x /app/docker-start.sh

EXPOSE ${PORT:-8080}

CMD ["/app/docker-start.sh"]
