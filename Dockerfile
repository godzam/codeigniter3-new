FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
		git \
		unzip \
		libzip-dev \
	&& docker-php-ext-install mysqli pdo pdo_mysql zip \
	&& a2enmod rewrite \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --no-interaction --optimize-autoloader \
	&& [ -f application/config/database.php ] || cp application/config/database.php.example application/config/database.php \
	&& mkdir -p application/cache application/logs \
	&& chown -R www-data:www-data application/cache application/logs

EXPOSE 80
