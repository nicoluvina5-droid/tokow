FROM php:8.2-cli

# Instalar extensiones de MySQL requeridas
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar todo el código del proyecto
COPY . /var/www/html

ENV PORT=8080
EXPOSE 8080

# Iniciar servidor ejecutable de PHP directamente en el puerto dinámico $PORT asignado por Railway
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT}"]
