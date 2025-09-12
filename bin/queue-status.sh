#!/bin/bash

# Laravel Queue Management Script
# Usage: ./bin/queue-status.sh [command]

# Load environment variables
if [ -f .env ]; then
    export $(cat .env | grep -v '#' | xargs)
fi

# Set defaults
QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}
DOCUMENT_ANALYSIS_QUEUE=${DOCUMENT_ANALYSIS_QUEUE:-document-analysis}
DOCUMENT_PROCESSING_QUEUE=${DOCUMENT_PROCESSING_QUEUE:-document-processing}

case "$1" in
  "status")
    echo "📊 Checking queue status..."
    docker-compose exec supervisor supervisorctl status
    ;;
  "start")
    echo "▶️ Starting all queue workers..."
    docker-compose exec supervisor supervisorctl start all
    ;;
  "stop")
    echo "⏹️ Stopping all queue workers..."
    docker-compose exec supervisor supervisorctl stop all
    ;;
  "restart")
    echo "🔄 Restarting all queue workers..."
    docker-compose exec supervisor supervisorctl restart all
    ;;
  "logs")
    if [ -n "$2" ]; then
      echo "📜 Showing logs for $2..."
      docker-compose exec supervisor tail -f /var/www/storage/logs/$2-worker.log
    else
      echo "📜 Showing general worker logs..."
      docker-compose exec supervisor tail -f /var/www/storage/logs/worker.log
    fi
    ;;
  "jobs")
    echo "🎯 Checking job statistics for $QUEUE_CONNECTION..."
    docker-compose exec app php artisan queue:monitor
    ;;
  "failed")
    echo "❌ Checking failed jobs..."
    docker-compose exec app php artisan queue:failed
    ;;
  "retry")
    if [ -n "$2" ]; then
      echo "🔁 Retrying job $2..."
      docker-compose exec app php artisan queue:retry "$2"
    else
      echo "🔁 Retrying all failed jobs..."
      docker-compose exec app php artisan queue:retry all
    fi
    ;;
  "flush")
    echo "🧹 Flushing all failed jobs..."
    docker-compose exec app php artisan queue:flush
    ;;
  "config")
    echo "⚙️ Current queue configuration:"
    echo "  QUEUE_CONNECTION: $QUEUE_CONNECTION"
    echo "  DOCUMENT_ANALYSIS_QUEUE: $DOCUMENT_ANALYSIS_QUEUE"
    echo "  DOCUMENT_PROCESSING_QUEUE: $DOCUMENT_PROCESSING_QUEUE"
    echo "  ANALYSIS_JOB_MAX_TRIES: ${ANALYSIS_JOB_MAX_TRIES:-3}"
    echo "  ANALYSIS_JOB_TIMEOUT: ${ANALYSIS_JOB_TIMEOUT:-300}"
    ;;
  "web")
    echo "🌐 Opening Supervisor web interface..."
    echo "URL: http://localhost:9001"
    echo "Username: admin"
    echo "Password: secret123"
    ;;
  *)
    echo "Laravel Queue Management"
    echo ""
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  status          Show worker status"
    echo "  start           Start all workers"
    echo "  stop            Stop all workers"  
    echo "  restart         Restart all workers"
    echo "  logs [worker]   Show worker logs (document-analysis, document-processing, or general)"
    echo "  jobs            Show job statistics"
    echo "  failed          Show failed jobs"
    echo "  retry [id|all]  Retry failed job(s)"
    echo "  flush           Remove all failed jobs"
    echo "  config          Show current queue configuration"
    echo "  web             Open supervisor web interface"
    echo ""
    echo "Examples:"
    echo "  $0 status"
    echo "  $0 config"
    echo "  $0 logs document-analysis"
    echo "  $0 retry all"
    ;;
esac