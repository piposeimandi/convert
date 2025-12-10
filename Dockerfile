# Dockerfile for CBR to EPUB converter web app
FROM php:8.2-cli

# Registrar repositorios con contrib/non-free y luego instalar 7-Zip + plugin RAR
RUN printf "deb http://deb.debian.org/debian bookworm main contrib non-free non-free-firmware\n" > /etc/apt/sources.list \
    && printf "deb http://security.debian.org/debian-security bookworm-security main contrib non-free non-free-firmware\n" >> /etc/apt/sources.list \
    && printf "deb http://deb.debian.org/debian bookworm-updates main contrib non-free non-free-firmware\n" >> /etc/apt/sources.list \
    && apt-get update \
     && apt-get install -y --no-install-recommends \
          libzip-dev \
         p7zip-full \
         p7zip-rar \
         unrar \
      && docker-php-ext-configure zip \
      && docker-php-ext-install zip \
     && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy project files
COPY . /app

# PHP overrides for large uploads/conversions
COPY docker/php-upload.ini /usr/local/etc/php/conf.d/uploads.ini

# Ensure upload/output directories exist with permissive permissions
RUN mkdir -p uploads converted \
    && chmod 775 uploads converted

EXPOSE 8111

CMD ["php", "-S", "0.0.0.0:8111", "-t", "/app"]
