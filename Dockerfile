# Optional development/test runtime. XAMPP does not use Docker.
FROM php:8.3-cli-bookworm
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
