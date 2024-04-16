FROM php:8.1-apache

# install the PHP extensions we need
RUN set -ex; \
        \
        if command -v a2enmod; then \
                a2enmod rewrite; \
        fi; \
        \
        savedAptMark="$(apt-mark showmanual)"; \
        \
        apt-get clean && apt-get update; \
        apt-get install -y --no-install-recommends \
                libjpeg-dev \
                libpng-dev \
                libpq-dev \
                libxml2-dev \
                g++ \
                make \
                automake \
                autoconf \
                libzip-dev \
                libwebp-dev \
        ; \
        \
        docker-php-ext-configure gd --enable-gd --with-jpeg --with-webp; \
        docker-php-ext-install -j "$(nproc)" \
                gd \
                opcache \
                mysqli \
                intl \
                exif \
                pdo \
                pdo_mysql \
                pdo_pgsql \
                xml \
                intl \
        ; \
        \
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
apt-mark auto '.*' > /dev/null; \
        apt-mark manual $savedAptMark; \
        ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
                | awk '/=>/ { print $3 }' \
                | sort -u \
                | xargs -r dpkg-query -S \
                | cut -d: -f1 \
                | sort -u \
                | xargs -rt apt-mark manual; \
        \
        apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
        rm -rf /var/lib/apt/lists/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
                echo 'opcache.memory_consumption=128'; \
                echo 'opcache.interned_strings_buffer=8'; \
                echo 'opcache.max_accelerated_files=4000'; \
                echo 'opcache.revalidate_freq=60'; \
                echo 'opcache.fast_shutdown=1'; \
                echo 'opcache.enable_cli=1'; \
        } > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN apt-get clean && apt-get update

RUN apt-get install -y --no-install-recommends \
    git \
    vim \
    curl \
    wget \
    mariadb-client \
    ssh-client \
    ssl-cert \
    libfontconfig \
    gnupg \
    jpegoptim \
    optipng \
    unzip
RUN docker-php-ext-install bcmath

# install redis
RUN pecl install redis

# install imagick
RUN apt-get update && apt-get install -y \
    imagemagick libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Add credentials so we can run composer in the container
RUN apt-get update \
    && apt-get -y install openssh-server
RUN mkdir -p /var/run/sshd

# authorize SSH connection with root account
RUN sed -ri 's/^#?PermitRootLogin\s+.*/PermitRootLogin yes/' /etc/ssh/sshd_config
RUN sed -ri 's/UsePAM yes/#UsePAM yes/g' /etc/ssh/sshd_config
RUN service ssh restart

# enable site
COPY apache-drupal-http.conf /etc/apache2/sites-available/000-default.conf
COPY apache-drupal-https.conf /etc/apache2/sites-available/default-ssl.conf
COPY my.cnf /etc/mysql/my.cnf
COPY drupal-php.ini /usr/local/etc/php/conf.d/
COPY entrypoint.sh /sbin/entrypoint.sh
COPY / /var/www/html

RUN mkdir -p /var/www/html/web/sites/default/files
RUN mkdir -p /var/www/html/web/sites/default/files/languages
RUN mkdir -p /var/www/html/config/keys

RUN chmod 777 /var/www/html/web/sites/default/files/
RUN chmod 777 /var/www/html/config/keys/

# chmod for entrypoint
RUN chmod +x /sbin/entrypoint.sh

# set env variable
ENV APACHE_DOCUMENT_ROOT /var/www/html/web

# enable ssl
RUN a2enmod ssl
RUN a2ensite default-ssl

# install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

SHELL ["/bin/bash", "--login", "-c"]

RUN curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.33.1/install.sh | bash

RUN nvm install 10.24.1
# install drush, THIS NO LONGER NEEDED,the repo been archive
# RUN wget -O drush.phar https://github.com/drush-ops/drush-launcher/releases/latest/download/drush.phar && \
#         chmod +x drush.phar && \
#         mv drush.phar /usr/local/bin/drush

RUN mkdir -p /usr/share/man/man1
RUN apt-get install default-jre -y

RUN java -version

#install clamav

RUN apt-get install clamav clamav-daemon -y

WORKDIR /var/www/html

# make the terminal prettier
RUN echo 'export PS1="\[\033[0;32m\][\u@docker]\[\033[0m\] in \[\033[0;34m\]\w\[\033[0m\] \n\$ "' >> /root/.bashrc
# Allow to run drush in terminal by simply call "drush <command>"
RUN echo 'export PATH="/var/www/html/vendor/bin/:$PATH"' >> /root/.bashrc


EXPOSE 22 80 443

RUN /sbin/entrypoint.sh
