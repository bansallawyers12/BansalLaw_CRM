# Reverb WebSocket – Production Nginx Fix (Option A)

Use this on the **production server** where `revapi.bansalcrm.com` is hosted so `wss://revapi.bansalcrm.com` works.

---

## 1. Ensure Reverb is running

On the production server:

```bash
# Start Reverb (or use Supervisor to keep it running)
php82 artisan reverb:start
```

Reverb must listen on **port 8080** (default). Check with:

```bash
# Linux: check if something is listening on 8080
ss -tlnp | grep 8080
# or
netstat -tlnp | grep 8080
```

---

## 2. Nginx: WebSocket proxy for revapi.bansalcrm.com

Create or edit an Nginx server block for `revapi.bansalcrm.com` that:

- Listens on **443** with SSL.
- Proxies WebSocket requests to **http://127.0.0.1:8080** (Reverb).

**Example config** (adjust paths to your system):

```nginx
# /etc/nginx/sites-available/revapi.bansalcrm.com
# Or include this in your main nginx.conf under http { }

map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 443 ssl http2;
    server_name revapi.bansalcrm.com;

    # SSL certificates (use your actual paths, e.g. Let's Encrypt)
    ssl_certificate     /etc/letsencrypt/live/revapi.bansalcrm.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/revapi.bansalcrm.com/privkey.pem;

    # WebSocket proxy to Laravel Reverb
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
```

**Important:**

- Replace `ssl_certificate` and `ssl_certificate_key` with the real paths for `revapi.bansalcrm.com`.
- `proxy_pass http://127.0.0.1:8080` must match the port Reverb uses (`REVERB_SERVER_PORT=8080` in `.env`).

---

## 3. Enable the site and reload Nginx

```bash
# If using sites-available/sites-enabled
sudo ln -sf /etc/nginx/sites-available/revapi.bansalcrm.com /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

---

## 4. Checklist

| Step | Action |
|------|--------|
| 1 | Reverb running on 8080 (`php artisan reverb:start` or Supervisor). |
| 2 | Nginx server block for `revapi.bansalcrm.com` on 443 with SSL. |
| 3 | `location /` proxies to `http://127.0.0.1:8080` with `Upgrade` and `Connection` headers. |
| 4 | DNS: `revapi.bansalcrm.com` A/AAAA points to this server. |
| 5 | Firewall: allow inbound 443. |
| 6 | Production `.env`: `REVERB_HOST=revapi.bansalcrm.com`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, and `VITE_REVERB_*` set; frontend built and deployed. |

---

## 5. Test from browser

Open the CRM on your production URL (for example `https://crm.example.com`), go to a page that uses messages. In DevTools → Network, filter by "WS". You should see a successful request to `wss://revapi.bansalcrm.com/app/...` with status **101 Switching Protocols**.

---

## 6. If it still fails

- **Nginx error log:** `sudo tail -f /var/log/nginx/error.log`
- **Reverb log:** `storage/logs/reverb.log` or the log channel set in `config/reverb.php`
- Confirm Reverb is bound to `0.0.0.0:8080` or `127.0.0.1:8080` (`REVERB_SERVER_HOST` in `.env`)
