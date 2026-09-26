# Image de production : PHP 8.2 + Apache (déploiement sur Render ou tout hébergeur Docker).
# La base MySQL/MariaDB est externe ; toute la configuration passe par les variables
# d'environnement (voir config/config.example.php et render.yaml).

# --- Étape 1 : dépendances Composer (sans les outils de développement) -------------
FROM composer:2 AS dependances
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --optimize-autoloader --ignore-platform-reqs --no-scripts

# --- Étape 2 : application -----------------------------------------------------------
FROM php:8.2-apache

# Extensions PHP : PDO MySQL, GD (QR codes et images des PDF), intl non requis.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev ca-certificates \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd \
 && apt-get purge -y --auto-remove \
 && rm -rf /var/lib/apt/lists/*

# PHP en production : pas d'affichage d'erreurs, pas de version exposée.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { echo 'expose_php = Off'; echo 'memory_limit = 256M'; echo 'date.timezone = Africa/Lubumbashi'; } \
      > "$PHP_INI_DIR/conf.d/zz-gestion-frais.ini"

# Apache : racine web = public/, en-têtes, point d'entrée unique.
RUN a2enmod headers \
 && rm -f /etc/apache2/sites-enabled/000-default.conf
COPY docker/apache.conf /etc/apache2/sites-enabled/gestion-frais.conf
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
 && echo 'ServerTokens Prod' >> /etc/apache2/apache2.conf \
 && echo 'ServerSignature Off' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=dependances /app/vendor ./vendor
COPY . .

# Configuration lue dans les variables d'environnement ; dossiers inscriptibles.
RUN cp config/config.example.php config/config.php \
 && mkdir -p recus storage/logs storage/cache storage/simulateur \
 && chown -R www-data:www-data recus storage \
 && chmod +x docker/entrypoint.sh

ENV APP_ENV=production \
    TRUST_PROXY=true \
    PORT=10000

EXPOSE 10000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
