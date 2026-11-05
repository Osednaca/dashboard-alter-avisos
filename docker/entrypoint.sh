#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

# Uploaded files belong to the persistent volume, never to a release/image.
# Never delete an existing directory: older installations may store real uploads here.
if [ -L public/storage ]; then
    if [ "$(readlink public/storage)" != "../storage/app/public" ]; then
        unlink public/storage
        ln -s ../storage/app/public public/storage
    fi
elif [ -e public/storage ]; then
    echo "ERROR: public/storage is a real directory. Back it up and merge its files into storage/app/public before replacing it with a symlink. No files were removed." >&2
    exit 1
else
    ln -s ../storage/app/public public/storage
fi

exec "$@"
