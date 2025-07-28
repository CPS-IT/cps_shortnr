FROM php:8.4-cli

# Accept build-time arguments
ARG USER_ID=1000
ARG GROUP_ID=1000

# create a group + user that match the host UID/GID
# create group and user only when necessary
RUN set -e; \
    if ! getent group ${GROUP_ID} >/dev/null 2>&1; then \
        groupadd -g ${GROUP_ID} appgroup; \
    else \
        groupmod -n appgroup $(getent group ${GROUP_ID} | cut -d: -f1); \
    fi; \
    if ! getent passwd ${USER_ID} >/dev/null 2>&1; then \
        useradd -l -u ${USER_ID} -g ${GROUP_ID} -m -s /bin/bash appuser; \
    else \
        usermod -l appuser -g ${GROUP_ID} $(getent passwd ${USER_ID} | cut -d: -f1); \
    fi

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

USER appuser
WORKDIR /var/www/html

# Keep container running
CMD ["tail", "-f", "/dev/null"]
