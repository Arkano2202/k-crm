FROM php:8.2-apache

# ===============================
# EXTENSIONES NECESARIAS PARA MYSQL
# ===============================
RUN docker-php-ext-install pdo pdo_mysql

# ===============================
# HABILITAR MOD_REWRITE
# ===============================
RUN a2enmod rewrite

# ===============================
# CONFIGURAR APACHE EN PUERTO 3005
# ===============================
RUN sed -i 's/Listen 80/Listen 3005/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:3005>/' /etc/apache2/sites-available/000-default.conf

# ===============================
# PERMITIR .htaccess
# ===============================
RUN echo '<Directory /var/www/html>\n\
  AllowOverride All\n\
  Require all granted\n\
</Directory>' \
> /etc/apache2/conf-available/allow-html.conf \
&& a2enconf allow-html

# ===============================
# COPIAR PROYECTO
# ===============================
COPY . /var/www/html/

# ===============================
# PERMISOS
# ===============================
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 3005

CMD ["apache2-foreground"]