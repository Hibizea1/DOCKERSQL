FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

# Installer outils et extensions de base (optimisé, nettoyage des caches)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git unzip zip libzip-dev libsodium-dev curl nano \
    && docker-php-ext-install zip mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Copier composer depuis l'image officielle Composer (binaire)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Activer les modules Apache utiles pour HTTPS et redirections.
RUN a2enmod ssl rewrite headers

# Créer dossier logs si nécessaire
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs \
    && chmod -R 755 /var/www/html/logs

# Copier les fichiers de configuration Apache personnalisés depuis le contexte de build
COPY config/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY config/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf

# Dossier cible pour les certificats montés depuis l'hôte.
RUN mkdir -p /etc/apache2/ssl

RUN a2ensite default-ssl || true

# Supprimer le répertoire php fourni par l'image si présent (nous montons le code depuis l'hôte)
RUN rm -rf /var/www/html/php || true

# Entrypoint script will handle composer install when container starts
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Travailler dans /var/www/html
WORKDIR /var/www/html

EXPOSE 80
EXPOSE 443

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
