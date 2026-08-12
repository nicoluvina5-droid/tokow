FROM php:8.2-apache

# Instalar extensiones de MySQL requeridas
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Copiar todo el código del proyecto al directorio web
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html

# Configurar Apache para adaptarse al puerto dinámico $PORT asignado por Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf

ENV PORT=80

CMD ["apache2-foreground"]
