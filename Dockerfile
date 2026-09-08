FROM php:8.2-apache

# Configurar diretório de trabalho
WORKDIR /var/www/html

# Instalar extensões PHP comuns (adicione as que seu projeto precisa)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite do Apache (importante para frameworks PHP)
RUN a2enmod rewrite

# Copiar arquivos do projeto
COPY . /var/www/html/

# Configurar permissões adequadas
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expor porta 80
EXPOSE 80

# Iniciar Apache
CMD ["apache2-foreground"]
