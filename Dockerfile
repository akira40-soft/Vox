FROM php:8.2-apache

# Install PostgreSQL client and development libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    postgresql-client \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update Apache configuration to allow .htaccess overrides and set DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set the working directory
WORKDIR /var/www/html

# Copy project files to the container
COPY . /var/www/html/

# Create sessions directory and set permissions
RUN mkdir -p /var/www/html/sessions /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/sessions /var/www/html/uploads

# Set PHP defaults for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    sed -i 's/display_errors = On/display_errors = Off/g' "$PHP_INI_DIR/php.ini" && \
    sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/g' "$PHP_INI_DIR/php.ini" && \
    sed -i 's/post_max_size = 8M/post_max_size = 50M/g' "$PHP_INI_DIR/php.ini"

# Expose port 80 (Render will route to this)
EXPOSE 80

# The default command is to start Apache in the foreground
CMD ["apache2-foreground"]
