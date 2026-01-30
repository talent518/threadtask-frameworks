#!/bin/bash

set -e

APP=$(realpath $(dirname $0))

if [ -d "$APP/bin" ]; then
  git -C "$APP/bin" clean -fdx
  if [ -d "$APP/bin/php-8.5" ]; then
    git -C "$APP/bin/php-8.5" clean -fdx
  fi
  if [ -d "$APP/bin/pecl-event" ]; then
    git -C "$APP/bin/pecl-event" clean -fdx
  fi
  if [ -d "$APP/bin/php-inotify" ]; then
    git -C "$APP/bin/php-inotify" clean -fdx
  fi
fi

docker run -d -p 5000:5000 --name threadtask-frameworks -v "$APP:/app" -it ubuntu:24.04
docker exec -it threadtask-frameworks /bin/apt update
docker exec -it threadtask-frameworks /bin/apt install -y git sudo curl net-tools ifstat sqlite3
docker exec -it threadtask-frameworks /bin/bash -c 'echo "ubuntu ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/ubuntu'
docker exec -it -u ubuntu threadtask-frameworks /app/run.sh
