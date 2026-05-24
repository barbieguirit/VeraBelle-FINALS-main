#!/bin/sh
set -e

# Remove any leftover MPM symlinks that would cause Apache to load multiple MPMs
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf || true
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf || true

# Try to disable and enable modules gracefully
a2dismod mpm_event mpm_worker || true
a2enmod mpm_prefork || true
a2enmod rewrite || true

# Exec the default command (apache2-foreground) or any provided CMD
exec "$@"
