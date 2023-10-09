## Set CRON job
Don't forget to start CRON of Laravel
```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```
## Set Telegram webhook

Using curl:
```
BOT_TOKEN=$(grep TELEGRAM_BOT_TOKEN .env | cut -d '=' -f2)
DOMAIN=$(grep TELEGRAM_WEBHOOK_DOMAIN .env | cut -d '=' -f2)
curl -F "url=$DOMAIN/telegram-webhook" "https://api.telegram.org/bot$BOT_TOKEN/setWebhook"
```

