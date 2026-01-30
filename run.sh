#!/bin/bash -l

set -e

ROOT_PATH=$(realpath $(dirname $0))

if ! command -v threadtask > /dev/null 2>&1; then
  BIN_PATH=$ROOT_PATH/bin
  PHPDIR=/opt/phptt
  PHPSO=php
  JOB=-j$(nproc)
  EXTENSION_DIR=$PHPDIR/lib/extensions

  if ! command -v git > /dev/null 2>&1; then
    sudo apt install -y git
  fi

  if ! command -v gcc > /dev/null 2>&1; then
    sudo apt install -y gcc
  fi

  if ! command -v g++ > /dev/null 2>&1; then
    sudo apt install -y g++
  fi

  if ! command -v make > /dev/null 2>&1; then
    sudo apt install -y make
  fi

  if [ ! -d "$BIN_PATH" ]; then
    git clone https://gitee.com/talent518/threadtask.git "$BIN_PATH"
  fi

  if [ ! -f "$PHPDIR/lib/lib${PHPSO}.so" ]; then
    TMPDIR=$BIN_PATH/php-8.5

    if [ ! -d "$TMPDIR" ]; then
      git clone --depth=1 https://gitee.com/talent518/php-src.git -b PHP-8.5 $TMPDIR
    fi

    pushd $TMPDIR

    if [ ! -x "./configure" ]; then
      sudo apt install -y autoconf
      ./buildconf
    fi

    if [ ! -f "./Makefile" ]; then
        sudo apt install -y pkg-config \
          bison re2c libldap-dev libonig-dev libedit-dev \
          libsnmp-dev libtidy-dev libzip-dev \
          libc-client2007e-dev libxslt1-dev libbz2-dev libcurl4-openssl-dev \
          libffi-dev libwebp-dev libjpeg-dev libxpm-dev libfreetype-dev libgmp-dev \
          libmysqlclient-dev libxml2-dev libkrb5-dev libgcrypt20-dev libsqlite3-dev \
          libsasl2-dev libexpat1-dev

        EXTENSION_DIR=$EXTENSION_DIR ./configure CFLAGS=-O2 CXXFLAGS=-O2 \
          --prefix=$PHPDIR --with-config-file-path=$PHPDIR/etc --with-config-file-scan-dir=$PHPDIR/etc/php.d \
          --enable-zts --enable-embed --disable-fpm --disable-phpdbg \
          --with-openssl --with-system-ciphers --with-zlib --enable-bcmath --with-bz2 --enable-calendar --with-curl \
          --enable-dba=shared --enable-exif --with-ffi --enable-ftp --enable-gd --with-webp --with-jpeg --with-xpm \
          --with-freetype --with-gettext --with-gmp --with-mhash --enable-intl --with-ldap --with-ldap-sasl --enable-mbstring \
          --with-mysqli=mysqlnd --enable-pcntl --with-pdo-mysql=mysqlnd --with-libedit --with-readline --enable-shmop \
          --with-snmp --enable-soap --enable-sockets --enable-sysvmsg --enable-sysvsem --enable-sysvshm --with-tidy \
          --with-expat --with-xsl --enable-zend-test=shared --with-zip --enable-mysqlnd
    fi

    make $JOB
    sudo make install $JOB

    sudo mkdir -p $PHPDIR/etc/php.d $PHPDIR/tmp
    sudo cp -vf php.ini-production $PHPDIR/etc/php.ini

    sudo sh -c "cat - >> $PHPDIR/etc/php.d/def.ini" <<!
date.timezone = Asia/Shanghai
sys_temp_dir = $PHPDIR/tmp
upload_tmp_dir = $PHPDIR/tmp
session.save_path = $PHPDIR/tmp
soap.wsdl_cache_dir = $PHPDIR/tmp

opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=100000
opcache.max_wasted_percentage=5
opcache.revalidate_freq=2
opcache.save_comments=1
opcache.lockfile_path = $PHPDIR/tmp
opcache.jit_buffer_size=128M
opcache.jit=1205
!

    if [ -f "/opt/lampp/var/mysql/mysql.sock" ]; then
      sudo sh -c "cat - >> $PHPDIR/etc/php.d/mysql.ini" <<!
pdo_mysql.default_socket = /opt/lampp/var/mysql/mysql.sock
mysqli.default_socket = /opt/lampp/var/mysql/mysql.sock
!
    fi

    popd
  fi

  export PATH=$PHPDIR/bin:$PATH

  if [ ! -f "$EXTENSION_DIR/event.so" ]; then
    TMPDIR=$BIN_PATH/pecl-event
    if [ ! -d "$TMPDIR" ]; then
      git clone --depth=1 https://bitbucket.org/osmanov/pecl-event $TMPDIR
    fi

    sudo apt install -y libevent-dev

    pushd $TMPDIR

    phpize
    ./configure --with-event-core --with-event-extra --disable-event-openssl --enable-event-sockets --enable-sockets
    make $JOB
    sudo make install $JOB

    popd

      sudo sh -c "cat - >> $PHPDIR/etc/php.d/event.ini" <<!
extension=event
!
  fi

  if [ ! -f "$EXTENSION_DIR/inotify.so" ]; then
    TMPDIR=$BIN_PATH/php-inotify
    if [ ! -d "$TMPDIR" ]; then
      git clone --depth=1 https://github.com/arnaud-lb/php-inotify.git $TMPDIR
    fi

    pushd $TMPDIR

    phpize
    ./configure
    make $JOB
    sudo make install $JOB

      sudo sh -c "cat - >> $PHPDIR/etc/php.d/inotify.ini" <<!
extension=inotify
!

    popd
  fi

  make --no-print-directory -C $BIN_PATH $JOB PHPDIR=$PHPDIR PHPSO=$PHPSO

  export PATH=$BIN_PATH:$PATH
fi

threadtask -- $(dirname $0)/index.php $*
