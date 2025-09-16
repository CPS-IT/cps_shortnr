FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    zip \
    intl \
    bcmath \
    opcache

# Install Xdebug
RUN pecl install xdebug-3.4.4 \
    && docker-php-ext-enable xdebug

# Copy PHP configuration
COPY php/*.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www/html

# Keep container running
CMD ["tail", "-f", "/dev/null"]
