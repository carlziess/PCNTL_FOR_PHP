#!/bin/sh
PHPVERSION=$(php --version|head -1|awk '{ds="-";print tolower($1)ds$2}')
EXEC="/server/$PHPVERSION/bin/php"
W1="/path/pcntl/w1.php"
W2="/path/pcntl/w2.php"
PID=$(ps aux|grep -e "$W1" -e "$W2"|grep -v grep|wc -l)
case "$1" in
    start)
        if [ $PID  -gt 0 ]
        then
                echo "Process is already running or crashed."
        else
                echo "Starting phpdaemon..."
                $EXEC $W1
                $EXEC $W2
        fi
        ;;
    stop)
        if [ $PID  -lt 0 ]
        then
                echo "Process is not running."
        else
                echo "Stopping ..."
                killall php
                echo "phpdaemon stopped."
        fi
        ;;
    restart)
        $0 stop && $0 start
        ;;
    *)
        echo "Usage: $0 {start|stop|restart}" >&2
        exit 1
        ;;
esac

