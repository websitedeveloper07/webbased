# Use the official PHP image with Apache
FROM php:8.2-apache

# Copy all files from the repo to the web root
COPY . /var/www/html/

# Expose port 10000 (Render uses this for web services)
EXPOSE 10000

# Change the Apache port to 10000 (Render requirement)
RUN sed -i 's/80/10000/' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Enable Apache rewrite module (useful for frameworks)
RUN a2enmod rewrite

# Start Apache in the foreground
CMD ["apache2-foreground"]
