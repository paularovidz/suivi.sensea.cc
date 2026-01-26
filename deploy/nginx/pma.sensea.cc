server {
    listen 80;
    server_name pma.sensea.cc;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name pma.sensea.cc;

    ssl_certificate /etc/ssl/cloudflare/sensea.cc.pem;
    ssl_certificate_key /etc/ssl/cloudflare/sensea.cc.key;

    # Restriction par IP
    allow 86.192.205.61;
    deny all;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
