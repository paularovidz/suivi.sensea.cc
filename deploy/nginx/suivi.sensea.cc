server {
    listen 80;
    server_name suivi.sensea.cc;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name suivi.sensea.cc;

    ssl_certificate /etc/ssl/cloudflare/sensea.cc.pem;
    ssl_certificate_key /etc/ssl/cloudflare/sensea.cc.key;

    root /home/deploy/apps/snoezelen/frontend/dist;
    index index.html;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    location /api/ {
        proxy_pass http://127.0.0.1:8080/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 15M;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
