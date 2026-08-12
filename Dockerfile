FROM php:8.2-apache

# Instalar extensiones de MySQL requeridas
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Copiar todo el código del proyecto al directorio web
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
