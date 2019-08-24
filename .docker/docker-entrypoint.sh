#!/bin/sh

echo "Setting up the host file for xdebug"
echo "$(ip -4 route list match 0/0 | cut -d' ' -f3) host.docker.internal" >> /etc/hosts

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php "$@"
fi

exec "$@"

# so that docker doesn't exist just after starting up
tail -F anything
