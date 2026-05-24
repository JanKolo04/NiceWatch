# NiceWatch Agent

Lightweight PHP CLI agent — collects host metrics and pushes them to a NiceWatch server.

## Install

```bash
composer install
cp config.php.dist config.php
# edit config.php: server_url + api_token (from `nicewatch:host:create` on the server)
```

## Commands

```bash
./bin/nicewatch-agent ping            # verify connectivity + token
./bin/nicewatch-agent run             # single checkin (push metrics)
./bin/nicewatch-agent run --dry-run   # show what would be sent, don't send
./bin/nicewatch-agent run --config=/etc/nicewatch/agent.php
```

## Production: systemd timer

See [`../docs/deploy-agent.md`](../docs/deploy-agent.md). Ready-to-use unit files in `systemd/`.

## What it collects (phase 1)

- CPU usage % (sampled over 200 ms), core count, load 1/5/15
- Memory: total / used / available / used %, swap
- Disks (`df -P -B1 -T`): mount, FS, total/used bytes, used %
- Network: per-interface RX/TX byte counters (from `/proc/net/dev`)
- Uptime, kernel name + release

## Requirements

- PHP 8.2+ CLI with `ext-json`, `ext-curl` (Guzzle)
- Linux with `/proc` (Debian/Ubuntu target)
- `df` available in PATH
