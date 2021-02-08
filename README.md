# Install

Download the PHAR from Releases on GitHub, and install somewhere (I put it in `/usr/local/bin`).

Create the file `/etc/monit-pagerduty.conf` with the contents:
```
key=<<Your PagerDuty Integration key>>
```

# Usage

```
monit-pagerduty [--trigger|--resolve] [service]
```

Either `--trigger` or `--resolve` is required. Trigger triggers an incident; Resolve resolves it.

`service` is optional; if not provided, the script will use the Monit variable `MONIT_SERVICE`. To resolve an incident you **must** use the same `service` value as you used to trigger it.

```
check process php7.4-fpm with pidfile /run/php/php7.4-fpm.pid
  start program = "/usr/sbin/service php7.4-fpm start" with timeout 60 seconds
  stop program  = "/usr/sbin/service php7.4-fpm stop"
  if failed unixsocket /run/php/php7.4-fpm-maple.sock then restart
  if failed unixsocket /run/php/php7.4-fpm-maple.sock
    then exec "/usr/local/bin/monit-pagerduty --trigger php7.4-fpm"
  else if succeeded
    then exec "/usr/local/bin/monit-pagerduty --resolve php7.4-fpm"
```

For the above, these are the variables MONIT sets and which are used to populate the event:
```
'MONIT_DATE' => 'Sun, 07 Feb 2021 17:22:49',
'MONIT_SERVICE' => 'php7.4-fpm',
'MONIT_HOST' => 'web-01',
'MONIT_EVENT' => 'Connection failed',
'MONIT_DESCRIPTION' => 'failed protocol test [DEFAULT] at /run/php/php7.4-fpm.sock -- Cannot create unix socket for /run/php/php7.4-fpm.sock',
'MONIT_PROCESS_PID' => '12098',
'MONIT_PROCESS_MEMORY' => '31644',
'MONIT_PROCESS_CHILDREN' => '26',
'MONIT_PROCESS_CPU_PERCENT' => '0.0',
```

I've also set up the following. This is untested, because triggering Monit events to try this out is a pain:
```
  check system $HOST
    if loadavg (1min) > 4 then
      exec "/usr/local/bin/monit-pagerduty --trigger"
    else if succeeded
      then exec "/usr/local/bin/monit-pagerduty --resolve"

    if loadavg (5min) > 2 then
      exec "/usr/local/bin/monit-pagerduty --trigger"
    else if succeeded
      then exec "/usr/local/bin/monit-pagerduty --resolve"
    if cpu usage > 95% for 10 cycles then
      exec "/usr/local/bin/monit-pagerduty --trigger"
    else if succeeded
      then exec "/usr/local/bin/monit-pagerduty --resolve"
    if memory usage > 75% then
      exec "/usr/local/bin/monit-pagerduty --trigger"
    else if succeeded
      then exec "/usr/local/bin/monit-pagerduty --resolve"
    if swap usage > 25% then
      exec "/usr/local/bin/monit-pagerduty --trigger"
    else if succeeded
      then exec "/usr/local/bin/monit-pagerduty --resolve"
```
