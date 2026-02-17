FROM php:8.2-apache

# Installer extensions nécessaires et libsodium
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev libsodium-dev nano \
    && docker-php-ext-install zip mysqli pdo pdo_mysql sodium \
    && docker-php-ext-enable mysqli pdo_mysql sodium

# Créer dossier logs si nécessaire
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs \
    && chmod -R 755 /var/www/html/logs

# Copier le fichier de configuration Apache custom
COPY /config/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN rm -rf /var/www/html/php
# Travailler dans /var/www/html
WORKDIR /var/www/html
EXPOSE 80
